<?php
require_once '../config/database.php';

try {
    $conn = Database::getConnection();
    
    // Verifica se a tabela existe
    $check_table = $conn->query("SHOW TABLES LIKE 'produtos'");
    if ($check_table->num_rows === 0) {
        die("A tabela 'produtos' não existe no banco de dados.");
    }
    
    // Verifica a estrutura da tabela
    $describe = $conn->query("DESCRIBE produtos");
    $columns = [];
    while ($row = $describe->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['id', 'nome', 'preco', 'imagem', 'categoria', 'campeonato'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (!empty($missing_columns)) {
        die("Colunas faltando na tabela 'produtos': " . implode(', ', $missing_columns));
    }
    
    // Verifica se há produtos na tabela
    $count = $conn->query("SELECT COUNT(*) as total FROM produtos")->fetch_assoc()['total'];
    echo "Estrutura do banco de dados está correta.<br>";
    echo "Total de produtos: " . $count . "<br>";
    
    // Mostra alguns produtos de exemplo
    $sample = $conn->query("SELECT id, nome, categoria, campeonato, status FROM produtos LIMIT 5");
    echo "<br>Exemplo de produtos:<br>";
    while ($row = $sample->fetch_assoc()) {
        echo "ID: {$row['id']} - Nome: {$row['nome']} - Categoria: {$row['categoria']} - Campeonato: {$row['campeonato']} - Status: {$row['status']}<br>";
    }
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>
