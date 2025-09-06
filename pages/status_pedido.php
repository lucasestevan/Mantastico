<?php
session_start();
$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_pedido === 0) {
    exit("Pedido não encontrado.");
}

$conn = new mysqli("localhost", "root", "", "mantastico");
if ($conn->connect_error) {
    exit("Erro de conexão com o banco de dados.");
}

// A sua busca já estava correta
$stmt = $conn->prepare("SELECT status_pagamento, codigo_pedido FROM pedidos WHERE id = ?");
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$conn->close();

if (!$pedido) {
    exit("Pedido não encontrado.");
}

$status = $pedido['status_pagamento'];
$titulo = '';
$mensagem = '';
$cor_fundo = '';
$icone = '';

switch ($status) {
    case 'approved':
        $titulo = 'Pagamento Aprovado!';
        $mensagem = 'Obrigado pela sua compra! Seu pedido foi recebido e já estamos preparando para o envio.';
        $cor_fundo = '#d4edda';
        $icone = '✔️';
        break;
    case 'in_process':
    case 'pending':
        $titulo = 'Pagamento Pendente';
        $mensagem = 'Seu pagamento está sendo processado. Você receberá uma confirmação por e-mail assim que for aprovado.';
        $cor_fundo = '#fff3cd';
        $icone = '⏳';
        break;
    case 'rejected':
        $titulo = 'Pagamento Recusado';
        $mensagem = 'Houve um problema ao processar seu pagamento. Por favor, verifique os dados do seu cartão ou tente um método de pagamento diferente.';
        $cor_fundo = '#f8d7da';
        $icone = '❌';
        break;
    default:
        $titulo = 'Status Desconhecido';
        $mensagem = 'Não foi possível determinar o status do seu pagamento. Entre em contato com o suporte.';
        $cor_fundo = '#e2e3e5';
        $icone = '❓';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status do Pedido - Mantástico</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .status-container { background-color: <?php echo $cor_fundo; ?>; border-radius: 10px; padding: 40px; text-align: center; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .icone { font-size: 4em; margin-bottom: 20px; }
        h1 { font-size: 2em; margin: 0 0 15px; }
        p { font-size: 1.1em; color: #333; line-height: 1.6; }
        .btn-voltar { display: inline-block; background: #333; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 30px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="status-container">
        <div class="icone"><?php echo $icone; ?></div>
        <h1><?php echo $titulo; ?></h1>
        <p><?php echo $mensagem; ?></p>
        <p><strong>Código do seu Pedido:</strong> #<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></p>
        <a href="../index.php" class="btn-voltar">Voltar para a Loja</a>
    </div>
</body>
</html>