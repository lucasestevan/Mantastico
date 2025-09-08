<?php
// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/email_errors.log');

// Verificar se o autoload do Composer existe
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Erro: O autoload do Composer não foi encontrado. Execute 'composer install' primeiro.\n");
}
require_once $autoloadPath;

// Verificar se o arquivo de configuração de email existe
if (!file_exists(__DIR__ . '/config/email.php')) {
    die("Erro: O arquivo de configuração de email não foi encontrado.\n");
}

// Carregar configurações de email
require_once __DIR__ . '/config/email.php';

// Testar envio de email
try {
    $mail = EmailConfig::getInstance()->getMailer();
    
    // Configurações do email de teste
    $mail->addAddress('seu_email@gmail.com', 'Seu Nome'); // Substitua pelo seu email
    $mail->Subject = 'Teste de Email - ' . date('d/m/Y H:i:s');
    
    $mail->isHTML(true);
    $mail->Body = '<h1>Teste de Email</h1><p>Este é um email de teste enviado pelo sistema.</p>';
    $mail->AltBody = 'Teste de Email\n\nEste é um email de teste enviado pelo sistema.';
    
    // Tentar enviar o email
    if ($mail->send()) {
        echo "Email de teste enviado com sucesso!\n";
    } else {
        echo "Falha ao enviar o email. Verifique o log de erros.\n";
        echo "Erro: " . $mail->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "Erro ao enviar email: " . $e->getMessage() . "\n";
    if (file_exists(__DIR__ . '/email_errors.log')) {
        echo "Verifique o arquivo email_errors.log para mais detalhes.\n";
    }
}

// Exibir configurações atuais
echo "\nConfigurações atuais:\n";
echo "SMTP Host: " . $mail->Host . "\n";
echo "SMTP Auth: " . ($mail->SMTPAuth ? 'Sim' : 'Não') . "\n";
echo "SMTP Username: " . $mail->Username . "\n";
echo "SMTP Port: " . $mail->Port . "\n";
echo "SMTP Secure: " . $mail->SMTPSecure . "\n";
?>
