<?php
require_once __DIR__ . '/../vendor/autoload.php';

class EmailConfig {
    private static $instance = null;
    private $mailer;
    private $adminEmail;

    private function __construct() {
        // Configuração de exibição de erros
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/email_errors.log');

        // Criar diretório de logs se não existir
        if (!file_exists(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }

        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Carrega as variáveis de ambiente
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }
        
        // Configurações do servidor SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['SMTP_USERNAME'] ?? '';
        $this->mailer->Password = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->mailer->SMTPSecure = $_ENV['SMTP_SECURE'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->SMTPDebug = 2; // Habilita saída de depuração detalhada
        $this->mailer->Debugoutput = function($str, $level) {
            error_log("SMTP Debug: $str");
        };
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Configurações do remetente e email do admin
        $this->adminEmail = $_ENV['SMTP_FROM_EMAIL'] ?? '';
        $senderEmail = $_ENV['SMTP_FROM_EMAIL'] ?? '';
        $senderName = $_ENV['SMTP_FROM_NAME'] ?? 'Mantástico';
        
        $this->mailer->setFrom($senderEmail, $senderName);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getMailer() {
        return $this->mailer;
    }

    public function getAdminEmail() {
        return $this->adminEmail;
    }
}
?>
