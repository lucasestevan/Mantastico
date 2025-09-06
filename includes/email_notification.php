<?php
require_once __DIR__ . '/../config/email.php';

class EmailNotification {
    private static function formatarMoeda($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private static function formatarData($data) {
        date_default_timezone_set('America/Sao_Paulo');
        return date('d/m/Y H:i', strtotime($data));
    }

    private static function getStatusPedido($status) {
        $statusMap = [
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'in_process' => 'Em processamento',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado'
        ];
        return $statusMap[$status] ?? $status;
    }

    public static function enviarEmailCliente($pedido, $cliente) {
        try {
            $mailer = EmailConfig::getInstance()->getMailer();
            
            $mailer->clearAddresses();
            $mailer->addAddress($cliente['email'], $cliente['nome']);
            
            $mailer->Subject = "Pedido {$pedido['codigo_pedido']} - Aprovado";
            
            // Corpo do email
            $body = "
            <h2>Olá {$cliente['nome']},</h2>
            <p>Seu pedido {$pedido['codigo_pedido']} foi aprovado com sucesso!</p>
            
            <h3>Detalhes do Pedido:</h3>
            <p>
            <strong>Data:</strong> " . date('d/m/Y', strtotime($pedido['data_pedido'])) . "<br>
            <strong>Status:</strong> " . self::getStatusPedido($pedido['status']) . "<br>
            <strong>Valor Total:</strong> " . self::formatarMoeda($pedido['valor_total']) . "
            </p>

            <h3>Endereço de Entrega:</h3>
            <p>
            {$pedido['endereco']}<br>
            {$pedido['cidade']} - {$pedido['estado']}<br>
            CEP: {$pedido['cep']}
            </p>

            <p>Em breve você receberá o código de rastreamento do seu pedido.</p>
            
            <p>Obrigado por comprar com a Mantástico!</p>
            ";
            
            $mailer->isHTML(true);
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            
            return $mailer->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar email para cliente: " . $e->getMessage());
            return false;
        }
    }

    public static function enviarEmailAdmin($pedido, $cliente) {
        try {
            $mailer = EmailConfig::getInstance()->getMailer();
            
            $mailer->clearAddresses();
            $mailer->addAddress(EmailConfig::getInstance()->getAdminEmail(), 'Admin Mantástico');
            
            $mailer->Subject = "Novo Pedido Aprovado {$pedido['codigo_pedido']}";
            
            // Lista de produtos do pedido
            $listaProdutos = "";
            
            // Verifica se produtos é uma string JSON e decodifica
            $produtos = is_string($pedido['produtos']) ? json_decode($pedido['produtos'], true) : $pedido['produtos'];
            
            if (!empty($produtos) && is_array($produtos)) {
                foreach ($produtos as $item) {
                    // Verifica se o item tem o formato esperado
                    if (is_array($item) && isset($item['id_produto'])) {
                        $listaProdutos .= "
                        - ID: {$item['id_produto']}";
                        
                        if (isset($item['nome'])) {
                            $listaProdutos .= ", Nome: {$item['nome']}";
                        }
                        
                        if (isset($item['qtd'])) {
                            $listaProdutos .= ", Quantidade: {$item['qtd']}";
                        }
                        
                        if (isset($item['preco'])) {
                            $listaProdutos .= ", Preço: " . self::formatarMoeda($item['preco']);
                        }
                        
                        if (!empty($item['nome_pers'])) {
                            $listaProdutos .= ", Personalização: {$item['nome_pers']}";
                        }
                        
                        $listaProdutos .= "<br>";
                    }
                }
            } else {
                $listaProdutos = "Não foi possível carregar a lista de produtos.";
            }
            
            // Corpo do email
            $body = "
            <h2>Novo Pedido Aprovado!</h2>
            
            <h3>Detalhes do Pedido #{$pedido['id']}</h3>
            <p>
            <strong>Data:</strong> " . date('d/m/Y', strtotime($pedido['data_pedido'])) . "<br>
            <strong>Cliente:</strong> {$cliente['nome']}<br>
            <strong>Email:</strong> {$cliente['email']}<br>
            <strong>Telefone:</strong> {$pedido['telefone']}<br>
            <strong>Valor Total:</strong> " . self::formatarMoeda($pedido['valor_total']) . "
            </p>

            <h3>Produtos:</h3>
            $listaProdutos

            <h3>Endereço de Entrega:</h3>
            <p>
            {$pedido['endereco']}<br>
            {$pedido['cidade']} - {$pedido['estado']}<br>
            CEP: {$pedido['cep']}
            </p>

            <p><a href='http://localhost/mantastico/admin/pedido_detalhes.php?id={$pedido['id']}'>Clique aqui para ver os detalhes completos do pedido</a></p>
            ";
            
            $mailer->isHTML(true);
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            
            return $mailer->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar email para admin: " . $e->getMessage());
            return false;
        }
    }
}
