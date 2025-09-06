<?php
session_start();
require_once '../config/database.php';
require_once '../config/log.php';

if (!isset($_GET['pedido_id'])) {
    header('Location: ../index.php');
    exit;
}

$pedido_id = intval($_GET['pedido_id']);
$conn = Database::getConnection();

// Buscar informações do pedido
$stmt = $conn->prepare("SELECT codigo_pedido, id_pagamento_externo, total FROM pedidos WHERE id = ?");
$stmt->bind_param("i", $pedido_id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();

if (!$pedido) {
    header('Location: ../index.php');
    exit;
}

// Configurar o Mercado Pago
require_once '../vendor/autoload.php';
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

MercadoPagoConfig::setAccessToken("TEST-4147091473469928-080413-d5ce3d5f3e0981e51e4d08c7bbbfad9a-152007619");
$client = new PaymentClient();

if (isset($_SESSION['pix_data']) && $_SESSION['pix_data']['pedido_id'] == $pedido_id) {
    $qr_code = $_SESSION['pix_data']['qr_code'];
    $qr_code_base64 = $_SESSION['pix_data']['qr_code_base64'];
    // Limpar os dados da sessão para não serem reutilizados indevidamente
    unset($_SESSION['pix_data']);
} else {
    try {
        $payment = $client->get($pedido['id_pagamento_externo']);
        $qr_code = $payment->point_of_interaction->transaction_data->qr_code;
        $qr_code_base64 = $payment->point_of_interaction->transaction_data->qr_code_base64;
    } catch (Exception $e) {
        logError("Erro ao buscar QR Code PIX: " . $e->getMessage());
        header('Location: status_pedido.php?id=' . $pedido_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - Mantástico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            text-align: center;
            color: #2c5b2d;
            margin-bottom: 30px;
        }
        .qr-code {
            text-align: center;
            margin-bottom: 30px;
        }
        .qr-code img {
            max-width: 300px;
            margin-bottom: 20px;
        }
        .pix-code {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            word-break: break-all;
            font-family: monospace;
        }
        .copy-button {
            background-color: #2c5b2d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 20px;
        }
        .copy-button:hover {
            background-color: #1e421f;
        }
        .instructions {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .instructions h2 {
            color: #2c5b2d;
            margin-top: 0;
        }
        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }
        .valor {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #2c5b2d;
            margin: 20px 0;
        }
        #status-message {
            display: none;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pagamento via PIX</h1>
        
        <div class="valor">
            Valor a pagar: R$ <?= number_format($pedido['total'], 2, ',', '.') ?>
        </div>

        <div class="qr-code">
            <img src="data:image/png;base64,<?= $qr_code_base64 ?>" alt="QR Code PIX">
        </div>

        <div class="instructions">
            <h2>Código PIX</h2>
            <p>Use o código abaixo para pagar via PIX:</p>
            <div class="pix-code"><?= $qr_code ?></div>
            <button class="copy-button" onclick="copyPixCode()">Copiar Código PIX</button>
        </div>

        <div class="instructions">
            <h2>Como pagar:</h2>
            <ol>
                <li>Abra o app do seu banco</li>
                <li>Escolha pagar via PIX</li>
                <li>Escaneie o QR Code ou cole o código PIX</li>
                <li>Confirme as informações e valor</li>
                <li>Confirme o pagamento</li>
            </ol>
        </div>

        <div id="status-message"></div>
    </div>

    <script>
        function copyPixCode() {
            const pixCode = `<?= $qr_code ?>`;
            navigator.clipboard.writeText(pixCode).then(() => {
                const button = document.querySelector('.copy-button');
                button.textContent = 'Código Copiado!';
                setTimeout(() => {
                    button.textContent = 'Copiar Código PIX';
                }, 2000);
            });
        }

        let checkCount = 0;
        const maxChecks = 288; // 24 horas (checando a cada 5 minutos)
        
        // Verificar status do pagamento a cada 30 segundos
        const checkInterval = setInterval(() => {
            checkCount++;
            
            fetch(`check_pix_status.php?pedido_id=<?= $pedido_id ?>`)
                .then(response => response.json())
                .then(data => {
                    const statusMessage = document.getElementById('status-message');
                    
                    if (data.status === 'approved') {
                        clearInterval(checkInterval);
                        statusMessage.style.display = 'block';
                        statusMessage.style.backgroundColor = '#d4edda';
                        statusMessage.style.color = '#155724';
                        statusMessage.textContent = 'Pagamento confirmado! Redirecionando...';
                        setTimeout(() => {
                            window.location.href = `status_pedido.php?id=<?= $pedido_id ?>`;
                        }, 2000);
                    } else if (data.status === 'pending') {
                        statusMessage.style.display = 'block';
                        statusMessage.style.backgroundColor = '#fff3cd';
                        statusMessage.style.color = '#856404';
                        statusMessage.textContent = 'Aguardando pagamento...';
                    } else if (checkCount >= maxChecks) {
                        clearInterval(checkInterval);
                        statusMessage.style.display = 'block';
                        statusMessage.style.backgroundColor = '#f8d7da';
                        statusMessage.style.color = '#721c24';
                        statusMessage.textContent = 'O tempo para pagamento expirou. Por favor, faça um novo pedido.';
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar status:', error);
                });
        }, 30000);
    </script>
</body>
</html>
