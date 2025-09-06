<?php
// Habilitar a exibição de todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // Para formatar a saída

require_once __DIR__ . '/../config/email.php';

try {
    $emailConfig = EmailConfig::getInstance();
    $mailer = $emailConfig->getMailer();
    $adminEmail = $emailConfig->getAdminEmail();

    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("O email do administrador não está configurado ou é inválido em config/email.php");
    }

    // Configurações do email de teste
    $mailer->clearAddresses();
    $mailer->addAddress($adminEmail, 'Admin Teste');
    $mailer->Subject = 'Teste de Envio de Email - Mantástico';
    $mailer->Body    = '<h1>Teste bem-sucedido!</h1><p>Se você está vendo este email, a configuração do PHPMailer está funcionando corretamente.</p>';
    $mailer->AltBody = 'Teste bem-sucedido! A configuração do PHPMailer está funcionando.';

    echo "Tentando enviar email para: " . htmlspecialchars($adminEmail) . "\n";
    echo "Usando o host: " . htmlspecialchars($mailer->Host) . " e usuário: " . htmlspecialchars($mailer->Username) . "\n\n";

    // Habilitar debug do SMTP
    $mailer->SMTPDebug = 2; // 2 para mensagens detalhadas do cliente e servidor
    $mailer->Debugoutput = 'html';

    $mailer->send();
    echo '<h2>Email de teste enviado com sucesso!</h2>';
    echo 'Verifique a caixa de entrada (e a pasta de spam) de ' . htmlspecialchars($adminEmail);

} catch (Exception $e) {
    echo '<h2>Ocorreu um erro ao enviar o email.</h2>';
    echo '<strong>Mensagem de Erro do PHPMailer:</strong> ' . $mailer->ErrorInfo;
    echo '<br><br>';
    echo '<strong>Mensagem de Erro Geral:</strong> ' . $e->getMessage();
}

echo "</pre>";
