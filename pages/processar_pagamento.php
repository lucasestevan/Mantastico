<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';
require_once '../config/log.php';
require_once '../includes/email_notification.php';

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

header('Content-Type: application/json');

// Suas credenciais de teste do Mercado Pago
MercadoPagoConfig::setAccessToken("TEST-4147091473469928-080413-d5ce3d5f3e0981e51e4d08c7bbbfad9a-152007619");

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos recebidos.']);
    exit;
}

logError("Dados recebidos do frontend: " . json_encode($data));

$conn = null;
try {
    $conn = Database::getConnection();
    $conn->begin_transaction();

    // 1. Calcular o total do pedido a partir do carrinho na sessão (mais seguro)
    $total_carrinho = 0;
    $custo_personalizacao = 20;
    $produtos_db = [];
    if (empty($_SESSION['carrinho'])) {
        throw new Exception("Carrinho vazio. Não é possível processar o pagamento.");
    }
    $ids_produtos = array_column($_SESSION['carrinho'], 'id_produto');
    $ids_produtos_unicos = array_unique($ids_produtos);

    if (!empty($ids_produtos_unicos)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos_unicos), '?'));
        $types = str_repeat('i', count($ids_produtos_unicos));
        $stmt_produtos = $conn->prepare("SELECT id, preco FROM produtos WHERE id IN ($placeholders)");
        $stmt_produtos->bind_param($types, ...$ids_produtos_unicos);
        $stmt_produtos->execute();
        $resultado = $stmt_produtos->get_result();
        while ($p = $resultado->fetch_assoc()) {
            $produtos_db[$p['id']] = $p;
        }
        $stmt_produtos->close();
    }

    foreach ($_SESSION['carrinho'] as $item) {
        if (isset($produtos_db[$item['id_produto']])) {
            $preco_item = $produtos_db[$item['id_produto']]['preco'] + (!empty($item['nome_pers']) ? $custo_personalizacao : 0);
            $total_carrinho += $preco_item * $item['qtd'];
        }
    }
    $transaction_amount_float = round($total_carrinho, 2);
    logError("Valor da transação (calculado no backend): " . $transaction_amount_float);

    // 2. Gerar código do pedido
    $codigo_pedido = "#MAN-" . strtoupper(uniqid());

    // 3. Inserir pedido no banco de dados
    $endereco_completo = $data->endereco->rua . ", " . $data->endereco->numero . " - " . $data->endereco->bairro . ", " . $data->endereco->cidade . " - " . $data->endereco->estado . ", " . $data->endereco->cep;
    logError("Endereço completo a ser salvo: " . $endereco_completo);
    
    // Serializa o carrinho de compras para salvar na coluna 'produtos'
    $produtos_serializados = json_encode($_SESSION['carrinho']);

    $sql_pedido = "INSERT INTO pedidos (codigo_pedido, produtos, nome_cliente, email_cliente, cliente_whatsapp, cliente_documento, endereco_cliente, total, parcelas, status_pagamento, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())";
    $stmt_pedido = $conn->prepare($sql_pedido);

    // Define as parcelas (se não existirem, usa 1)
    $parcelas = isset($data->installments) ? $data->installments : 1;
    // Define o documento (se não existir, usa string vazia)
    $documento = isset($data->cliente_documento) ? $data->cliente_documento : '';

    $stmt_pedido->bind_param(
        "sssssssdi",
        $codigo_pedido,
        $produtos_serializados,
        $data->nome_cliente,
        $data->payer->email,
        $data->cliente_whatsapp,
        $documento,
        $endereco_completo,
        $transaction_amount_float,
        $parcelas
    );
    $stmt_pedido->execute();
    $pedido_id = $stmt_pedido->insert_id;
    $stmt_pedido->close();

    // 4. Inserir itens do pedido
    $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);
    foreach ($_SESSION['carrinho'] as $item) {
        if (isset($produtos_db[$item['id_produto']])) {
            $produto = $produtos_db[$item['id_produto']];
            $preco_final_item = $produto['preco'] + (!empty($item['nome_pers']) ? $custo_personalizacao : 0);
            $stmt_item->bind_param("iiid", $pedido_id, $item['id_produto'], $item['qtd'], $preco_final_item);
            $stmt_item->execute();
        }
    }
    $stmt_item->close();

    // 5. Montar e processar o pagamento com o Mercado Pago
    $client = new PaymentClient();

    // Separar nome e sobrenome para a API do MP
    $nome_parts = explode(' ', $data->nome_cliente, 2);
    $primeiro_nome = $nome_parts[0];
    $sobrenome = !empty($nome_parts[1]) ? $nome_parts[1] : '';

    $request = [
        "transaction_amount" => $transaction_amount_float,
        "description" => "Pedido da Loja Mantástico #" . $codigo_pedido,
        "external_reference" => $pedido_id,
        "payer" => [
            "email" => $data->payer->email,
            "first_name" => $primeiro_nome,
            "last_name" => $sobrenome,
            "identification" => [
                "type" => "CPF",
                "number" => $documento // Usando a variável $documento definida na inserção do pedido
            ],
            "address" => [
                "zip_code" => str_replace('-', '', $data->endereco->cep),
                "street_name" => $data->endereco->rua,
                "street_number" => $data->endereco->numero,
                "neighborhood" => $data->endereco->bairro,
                "city" => $data->endereco->cidade,
                "federal_unit" => $data->endereco->estado
            ]
        ]
    ];

    // ===================================================================
    // AQUI ESTÁ A CORREÇÃO PRINCIPAL
    // Verificamos explicitamente qual é o método de pagamento
    // ===================================================================
    if (isset($data->payment_method_id) && ($data->payment_method_id === 'pix' || $data->payment_method_id === 'bank_transfer')) {
        logError("Iniciando processamento PIX ou Transferência");
        $request["payment_method_id"] = $data->payment_method_id;
        $request["date_of_expiration"] = date('Y-m-d\TH:i:s.000P', strtotime('+24 hours'));
    
    } else if (isset($data->token)) {
        logError("Iniciando processamento Cartão de Crédito");
        $request["token"] = $data->token;
        $request["installments"] = $data->installments;
        $request["payment_method_id"] = $data->payment_method_id;
        $request["issuer_id"] = (int) $data->issuer_id;

    } else {
        // Se não for PIX, Transferência e não tiver token, é um erro.
        throw new Exception("Token do cartão não fornecido ou método de pagamento inválido.");
    }

    logError("Request montado para MP: " . json_encode($request));
    $payment = $client->create($request);
    logError("Resposta completa MP: " . json_encode($payment));

    if (!$payment || !isset($payment->id)) {
        throw new Exception("Falha ao criar pagamento no Mercado Pago.");
    }

    // 6. Atualizar pedido com o ID do pagamento e status
    $status_pagamento = $payment->status ?? 'pendente';
    $sql_update = "UPDATE pedidos SET id_pagamento_externo = ?, status_pagamento = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssi", $payment->id, $status_pagamento, $pedido_id);
    $stmt_update->execute();
    $stmt_update->close();

    // 7. Enviar email de confirmação se o pagamento já foi aprovado (ex: Cartão de Crédito)
    if ($status_pagamento === 'approved') {
        // Para o email, precisamos dos nomes dos produtos. Vamos buscá-los.
        $nomes_produtos = [];
        if (!empty($ids_produtos_unicos)) {
            $stmt_nomes = $conn->prepare("SELECT id, nome FROM produtos WHERE id IN ($placeholders)");
            $stmt_nomes->bind_param($types, ...$ids_produtos_unicos);
            $stmt_nomes->execute();
            $resultado_nomes = $stmt_nomes->get_result();
            while ($p = $resultado_nomes->fetch_assoc()) {
                $nomes_produtos[$p['id']] = $p['nome'];
            }
            $stmt_nomes->close();
        }

        $produtos_para_email = [];
        foreach ($_SESSION['carrinho'] as $item_no_carrinho) {
            if (isset($produtos_db[$item_no_carrinho['id_produto']])) {
                $produto_atual_db = $produtos_db[$item_no_carrinho['id_produto']];
                $produtos_para_email[] = [
                    'nome' => $nomes_produtos[$item_no_carrinho['id_produto']] ?? 'Produto Desconhecido',
                    'quantidade' => $item_no_carrinho['qtd'],
                    'preco' => $produto_atual_db['preco'],
                    'personalizacao' => $item_no_carrinho['nome_pers'] ?? ''
                ];
            }
        }

        $cliente_info = [
            'nome' => $data->nome_cliente,
            'email' => $data->payer->email
        ];

        $pedido_info = [
            'codigo_pedido' => $codigo_pedido,
            'data_pedido' => date('Y-m-d H:i:s'),
            'status' => $status_pagamento,
            'valor_total' => $transaction_amount_float,
            'endereco' => $endereco_completo,
            'cidade' => $data->endereco->cidade,
            'estado' => $data->endereco->estado,
            'cep' => $data->endereco->cep,
            'produtos' => json_encode($produtos_para_email),
            'id' => $pedido_id,
            'telefone' => $data->cliente_whatsapp
        ];

        // Enviar os emails
        $email_cliente_enviado = EmailNotification::enviarEmailCliente($pedido_info, $cliente_info);
        $email_admin_enviado = EmailNotification::enviarEmailAdmin($pedido_info, $cliente_info);

        // Marcar que o email foi enviado
        if ($email_cliente_enviado && $email_admin_enviado) {
            $stmt_email_sent = $conn->prepare("UPDATE pedidos SET email_enviado = 1 WHERE id = ?");
            $stmt_email_sent->bind_param("i", $pedido_id);
            $stmt_email_sent->execute();
            $stmt_email_sent->close();
            logError("Emails de confirmação para o pedido {$pedido_id} (Aprovação Direta) enviados.");
        } else {
            logError("Falha ao enviar emails para o pedido {$pedido_id} (Aprovação Direta).");
        }
    }

    // 8. Preparar resposta para o frontend
    $response_data = [
        'status' => $payment->status,
        'pedido_id' => $pedido_id
    ];

    if ($payment->payment_method_id === 'pix') {
        logError("PIX gerado com sucesso - ID: " . $payment->id);
        $_SESSION['pix_data'] = [
            'pedido_id' => $pedido_id,
            'qr_code' => $payment->point_of_interaction->transaction_data->qr_code,
            'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64
        ];
        $response_data['payment_type'] = 'pix';
        $response_data['redirect_url'] = "pix_qrcode.php?pedido_id=" . $pedido_id;
    }

    // Limpa o carrinho após o sucesso
    unset($_SESSION['carrinho']);
    
    $conn->commit();
    logError("Enviando resposta de sucesso - Pedido ID: " . $pedido_id);
    echo json_encode($response_data);

} catch (MPApiException $e) {
    if ($conn) $conn->rollback();
    http_response_code($e->getApiResponse()->getStatusCode());
    $api_error_content = $e->getApiResponse()->getContent();
    logError("Erro da API do Mercado Pago: " . $e->getMessage() . " | Resposta: " . json_encode($api_error_content));
    echo json_encode($api_error_content);

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    http_response_code(400); // Bad Request
    logError("Erro geral no processamento: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
