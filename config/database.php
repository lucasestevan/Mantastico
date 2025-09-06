<?php
/**
 * Classe para gerenciar a conexão com o banco de dados.
 * Utiliza o padrão Singleton para garantir uma única instância da conexão.
 */
class Database {
    private static $host = "localhost";
    private static $user = "root";
    private static $pass = "";
    private static $db = "mantastico";
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                self::$conn = new mysqli(self::$host, self::$user, self::$pass, self::$db);
                self::$conn->set_charset("utf8mb4");
            } catch (mysqli_sql_exception $e) {
                error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
                throw new Exception("Não foi possível conectar ao banco de dados. Verifique se o XAMPP está rodando e o banco de dados está criado.");
            }
        }
        return self::$conn;
    }
}
?>