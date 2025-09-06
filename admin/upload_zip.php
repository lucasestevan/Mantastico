<?php
// Lida apenas com o upload e extração do ZIP, e retorna a lista de pastas.
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['zip_file'])) {
    if ($_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
        $zip_path = $_FILES['zip_file']['tmp_name'];
        $zip = new ZipArchive;

        if ($zip->open($zip_path) === TRUE) {
            $extract_path = 'uploads_pendentes/';
            if (!is_dir($extract_path)) {
                mkdir($extract_path, 0777, true);
            }
            // Limpa a pasta antes de extrair
            $files_to_delete = glob($extract_path . '*/*'); 
            foreach($files_to_delete as $file){ if(is_file($file)) { unlink($file); } }
            $dirs_to_delete = glob($extract_path . '*');
            foreach($dirs_to_delete as $dir){ if(is_dir($dir)) { rmdir($dir); } }


            $zip->extractTo($extract_path);
            $zip->close();

            $pastas_a_processar = [];
            $pastas = scandir($extract_path);
            foreach ($pastas as $nome_pasta) {
                if ($nome_pasta[0] !== '.' && $nome_pasta !== '__MACOSX' && is_dir($extract_path . $nome_pasta)) {
                    $pastas_a_processar[] = $nome_pasta;
                }
            }
            
            if (empty($pastas_a_processar)) {
                echo json_encode(['success' => false, 'message' => 'O arquivo .ZIP não continha pastas válidas.']);
            } else {
                echo json_encode(['success' => true, 'pastas' => $pastas_a_processar]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Falha ao abrir o arquivo .ZIP.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no upload do arquivo.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
}
?>