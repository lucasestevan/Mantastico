<?php
// Forçar a exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../config/log.php';
require_once '../vendor/autoload.php';
require_once '../includes/email_notification.php';

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

header('Content-Type: application/json');

if (!isset($_GET['pedido_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do pedido não fornecido.']);
    exit;
}

$pedido_id = intval($_GET['pedido_id']);
$conn = Database::getConnection();

// 1. Buscar o status ATUAL do pedido no nosso banco
$stmt_status_atual = $conn->prepare("SELECT status_pagamento, id_pagamento_externo, email_enviado FROM pedidos WHERE id = ?");
$stmt_status_atual->bind_param("i", $pedido_id);
$stmt_status_atual->execute();
$pedido_atual = $stmt_status_atual->get_result()->fetch_assoc();
$stmt_status_atual->close();

if (!$pedido_atual) {
    http_response_code(404);
    echo json_encode(['error' => 'Pedido não encontrado em nosso sistema.']);
    $conn->close();
    exit;
}

$status_anterior = $pedido_atual['status_pagamento'];
$email_ja_enviado = $pedido_atual['email_enviado'];

try {
    // Buscar informações reais do pagamento
    $payment = $client->get($pedido_atual['id_pagamento_externo']);

    // 3. Atualizar o status no banco de dados, se houver mudança
    if ($novo_status && $novo_status !== $status_anterior) {
        $stmt_update = $conn->prepare("UPDATE pedidos SET status_pagamento = ? WHERE id = ?");
        $stmt_update->bind_param("si", $novo_status, $pedido_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    // 4. Se o pagamento foi APROVADO e o email ainda NÃO FOI ENVIADO
    if ($novo_status === 'approved' && !$email_ja_enviado) {
        // Buscar todos os dados necessários para o email
        $stmt_full_pedido = $conn->prepare("SELECT p.*, GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.nome_produto, ' - R$ ', FORMAT(pi.preco_unitario, 2, 'de_DE')) SEPARATOR '\n') as produtos_formatados 
                                           FROM pedidos p 
                                           LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id 
                                           WHERE p.id = ? 
                                           GROUP BY p.id");
        $stmt_full_pedido->bind_param("i", $pedido_id);
        $stmt_full_pedido->execute();
        $pedido_completo = $stmt_full_pedido->get_result()->fetch_assoc();
        $stmt_full_pedido->close();
        
        // Buscar os itens do pedido separadamente
        $stmt_itens = $conn->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
        $stmt_itens->bind_param("i", $pedido_id);
        $stmt_itens->execute();
        $itens_result = $stmt_itens->get_result();
        $itens = [];
        while ($item = $itens_result->fetch_assoc()) {
            $itens[] = $item;
        }
        $stmt_itens->close();

        if ($pedido_completo) {
            // Montar os arrays para a função de email
            $cliente_info = [
                'nome' => $pedido_completo['nome_cliente'],
                'email' => $pedido_completo['email_cliente']
            ];
            
            $endereco_completo = $pedido_completo['endereco_cliente'];
            $cidade = '';
            $estado = '';
            $cep = '';

            if (preg_match('/,([^,]+)-([^,]+),([^,]+)$/', $endereco_completo, $matches)) {
                $cidade = trim($matches[1]);
                $estado = trim($matches[2]);
                $cep = trim($matches[3]);
            }

            $pedido_info = [
                'codigo_pedido' => $pedido_completo['codigo_pedido'],
                'data_pedido' => date('Y-m-d H:i:s'), // Usar a data/hora atual
                'status' => $novo_status,
                'valor_total' => $pedido_completo['total'],
                'endereco' => $endereco_completo,
                'cidade' => $cidade,
                'estado' => $estado,
                'cep' => $cep,
                'produtos' => $itens,
                'id' => $pedido_id,
                'telefone' => $pedido_completo['cliente_whatsapp']
            ];

            // Enviar os emails com logs detalhados
            logError("Tentando enviar email para o cliente: " . $cliente_info['email']);
            $email_cliente_enviado = EmailNotification::enviarEmailCliente($pedido_info, $cliente_info);
            logError("Resultado do envio para o cliente: " . ($email_cliente_enviado ? 'Sucesso' : 'Falha'));
            
            logError("Tentando enviar email para o admin");
            $email_admin_enviado = EmailNotification::enviarEmailAdmin($pedido_info, $cliente_info);
            logError("Resultado do envio para o admin: " . ($email_admin_enviado ? 'Sucesso' : 'Falha'));

            // Marcar que o email foi enviado para não enviar de novo
            if ($email_cliente_enviado && $email_admin_enviado) {
                $stmt_email_sent = $conn->prepare("UPDATE pedidos SET email_enviado = 1 WHERE id = ?");
                $stmt_email_sent->bind_param("i", $pedido_id);
                $stmt_email_sent->execute();
                $stmt_email_sent->close();
                logError("Emails de confirmação para o pedido {$pedido_id} enviados com sucesso.");
            } else {
                logError("Falha ao enviar um ou ambos os emails para o pedido {$pedido_id}.");
            }
        }
    }
    
    // 5. Retornar o status para o frontend
    $response_array = [
        'status' => $novo_status,
        'detail' => $payment->status_detail
    ];
    echo json_encode($response_array);

} catch (Exception $e) {
    logError("Erro ao verificar status do pagamento (Pedido ID: {$pedido_id}): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao verificar status do pagamento.']);
} finally {
    if ($conn) {
        $conn->close();
    }
}