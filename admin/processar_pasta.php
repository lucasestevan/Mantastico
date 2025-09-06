<?php
// Este script processa UMA única pasta por vez.
header('Content-Type: application/json');

function otimizar_imagem($caminho_origem, $caminho_destino, $qualidade = 85, $tamanho_max = 1200) {
    // ... (cole a sua função de otimização de imagem aqui)
    $info = getimagesize($caminho_origem);
    if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $imagem = imagecreatefromjpeg($caminho_origem); break;
        case 'image/png': $imagem = imagecreatefrompng($caminho_origem); break;
        case 'image/webp': $imagem = imagecreatefromwebp($caminho_origem); break;
        default: return rename($caminho_origem, $caminho_destino);
    }
    $largura_original = imagesx($imagem); $altura_original = imagesy($imagem);
    $ratio = $largura_original / $altura_original;
    if ($largura_original > $tamanho_max || $altura_original > $tamanho_max) {
        if ($ratio > 1) { $nova_largura = $tamanho_max; $nova_altura = $tamanho_max / $ratio; } 
        else { $nova_altura = $tamanho_max; $nova_largura = $tamanho_max * $ratio; }
    } else { $nova_largura = $largura_original; $nova_altura = $altura_original; }
    $imagem_redimensionada = imagecreatetruecolor($nova_largura, $nova_altura);
    if ($mime == 'image/png' || $mime == 'image/webp') {
        imagecolortransparent($imagem_redimensionada, imagecolorallocatealpha($imagem_redimensionada, 0, 0, 0, 127));
        imagealphablending($imagem_redimensionada, false);
        imagesavealpha($imagem_redimensionada, true);
    }
    imagecopyresampled($imagem_redimensionada, $imagem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura_original, $altura_original);
    $sucesso = imagejpeg($imagem_redimensionada, $caminho_destino, $qualidade);
    imagedestroy($imagem); imagedestroy($imagem_redimensionada);
    return $sucesso;
}

$conn = new mysqli("localhost", "root", "", "mantastico");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão.']);
    exit;
}

$nome_pasta = $_POST['pasta'] ?? '';
$preco_lote = isset($_POST['preco']) ? floatval($_POST['preco']) : 0.00;
$categoria_lote = isset($_POST['categoria']) ? trim($_POST['categoria']) : 'Geral';
// Capturando o campeonato
$campeonato_lote = isset($_POST['campeonato']) ? trim($_POST['campeonato']) : null;

if (empty($nome_pasta)) {
    echo json_encode(['success' => false, 'message' => 'Nome da pasta não fornecido.']);
    exit;
}

$extract_path = 'uploads_pendentes/';
$caminho_completo_pasta = $extract_path . $nome_pasta;

if (is_dir($caminho_completo_pasta)) {
    $nome_produto = $nome_pasta; 
    $imagens_na_pasta = glob($caminho_completo_pasta . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    
    if (!empty($imagens_na_pasta)) {
        $nomes_arquivos_imagens = [];
        foreach ($imagens_na_pasta as $imagem_path) {
            $novo_nome_arquivo = uniqid() . '-' . pathinfo(basename($imagem_path), PATHINFO_FILENAME) . '.jpg';
            if (otimizar_imagem($imagem_path, '../assets/images/' . $novo_nome_arquivo)) {
                 $nomes_arquivos_imagens[] = $novo_nome_arquivo;
            }
        }

        $lista_de_imagens = implode(',', $nomes_arquivos_imagens);
        
        // Query INSERT atualizada
        $stmt = $conn->prepare("INSERT INTO produtos (nome, preco, imagem, categoria, campeonato) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsss", $nome_produto, $preco_lote, $lista_de_imagens, $categoria_lote, $campeonato_lote);
        
        if ($stmt->execute()) {
            array_map('unlink', glob("$caminho_completo_pasta/*.*"));
            rmdir($caminho_completo_pasta);
            echo json_encode(['success' => true, 'message' => "Produto '{$nome_produto}' cadastrado."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Erro ao salvar '{$nome_pasta}' no banco."]);
        }
    } else {
        rmdir($caminho_completo_pasta);
        echo json_encode(['success' => false, 'message' => "Nenhuma imagem em '{$nome_pasta}'."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Pasta '{$nome_pasta}' não encontrada."]);
}

$conn->close();
?>