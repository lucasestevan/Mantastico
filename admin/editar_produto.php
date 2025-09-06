<?php
include 'includes/header.php';

$conn = new mysqli("localhost", "root", "", "mantastico");
if ($conn->connect_error) die("Erro de conexão");

$id_produto = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_produto === 0) {
    echo "ID do produto inválido.";
    exit;
}

$message = '';

// --- LÓGICA PRINCIPAL PARA SALVAR AS ALTERAÇÕES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Atualiza os dados de texto (nome, preço, etc.)
    $nome = $_POST['nome'];
    $preco = floatval($_POST['preco']);
    $categoria = $_POST['categoria'];
    $campeonato = $_POST['campeonato'];
    
    $stmt_update = $conn->prepare("UPDATE produtos SET nome=?, preco=?, categoria=?, campeonato=? WHERE id=?");
    $stmt_update->bind_param("sdssi", $nome, $preco, $categoria, $campeonato, $id_produto);
    $stmt_update->execute();

    // 2. Processa as imagens existentes (quais manter)
    $imagens_para_manter = $_POST['manter_imagem'] ?? [];
    
    // 3. Identifica qual foi selecionada como principal
    $foto_principal_selecionada = $_POST['foto_principal'] ?? null;

    // 4. Processa o upload de novas imagens
    $imagens_novas = [];
    if (isset($_FILES['novas_imagens']) && !empty($_FILES['novas_imagens']['name'][0])) {
        $total_novas_imagens = count($_FILES['novas_imagens']['name']);
        for ($i = 0; $i < $total_novas_imagens; $i++) {
            if ($_FILES['novas_imagens']['error'][$i] === UPLOAD_ERR_OK) {
                $nome_arquivo_novo = uniqid() . '-' . basename($_FILES['novas_imagens']['name'][$i]);
                $caminho_destino = '../assets/images/' . $nome_arquivo_novo;
                if (move_uploaded_file($_FILES['novas_imagens']['tmp_name'][$i], $caminho_destino)) {
                    $imagens_novas[] = $nome_arquivo_novo;
                }
            }
        }
    }
    
    // 5. Junta as imagens mantidas com as novas
    $imagens_finais = array_merge($imagens_para_manter, $imagens_novas);
    
    // 6. Reordena a lista para colocar a foto principal no início
    if ($foto_principal_selecionada && in_array($foto_principal_selecionada, $imagens_finais)) {
        // Remove a foto principal da sua posição atual
        $imagens_finais = array_diff($imagens_finais, [$foto_principal_selecionada]);
        // Adiciona a foto principal no início do array
        array_unshift($imagens_finais, $foto_principal_selecionada);
    }

    // 7. Salva a nova lista ordenada no banco de dados
    $lista_imagens_string = implode(',', $imagens_finais);
    $stmt_img = $conn->prepare("UPDATE produtos SET imagem = ? WHERE id = ?");
    $stmt_img->bind_param("si", $lista_imagens_string, $id_produto);
    $stmt_img->execute();

    $message = "<div class='alert alert-success'>Produto atualizado com sucesso!</div>";
    
    // 8. Apaga do servidor os arquivos de imagem que foram desmarcados
    $imagens_existentes = explode(',', $_POST['imagens_existentes']);
    $imagens_a_remover = array_diff($imagens_existentes, $imagens_para_manter);
     foreach ($imagens_a_remover as $img_remover) {
        if(empty(trim($img_remover))) continue;
        $caminho_arquivo = '../assets/images/' . trim($img_remover);
        if (file_exists($caminho_arquivo)) {
            unlink($caminho_arquivo);
        }
    }
}

// Busca os dados atualizados do produto para exibir no formulário
$stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$produto = $stmt->get_result()->fetch_assoc();

if (!$produto) { exit("Produto não encontrado."); }

$imagens_produto = !empty($produto['imagem']) ? explode(',', $produto['imagem']) : [];
?>
<style>
    .galeria-edicao { display: flex; flex-wrap: wrap; gap: 15px; }
    .imagem-container { position: relative; border: 2px solid #ddd; padding: 5px; border-radius: 5px; text-align: center; }
    .imagem-container img { width: 150px; height: 150px; object-fit: cover; display: block; }
    .imagem-container .form-check { position: absolute; top: 10px; left: 10px; background-color: rgba(255,255,255,0.8); padding: 5px; border-radius: 50%; }
    /* Estilo para a foto principal selecionada */
    .imagem-container.principal {
        border-color: #0d6efd; /* Azul do Bootstrap */
        box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
    }
</style>

<h2>Editar Camisa: <?= htmlspecialchars($produto['nome']) ?></h2>
<a href="produtos.php" class="btn btn-secondary mb-3">Voltar para a Lista</a>

<?= $message ?>

<form method="post" enctype="multipart/form-data" class="mb-4">
    <div class="card">
        <div class="card-header">Dados do Produto</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nome do Produto</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Preço</label>
                    <input type="number" step="0.01" name="preco" class="form-control" value="<?= htmlspecialchars($produto['preco']) ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Categoria</label>
                    <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($produto['categoria']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Campeonato</label>
                    <input type="text" name="campeonato" class="form-control" value="<?= htmlspecialchars($produto['campeonato'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">Gerenciar Imagens</div>
        <div class="card-body">
            <h5>Imagens Atuais</h5>
            <p>Selecione a foto principal e desmarque as que deseja excluir.</p>
            
            <input type="hidden" name="imagens_existentes" value="<?= htmlspecialchars($produto['imagem']) ?>">

            <div class="galeria-edicao mb-3">
                <?php if (!empty($imagens_produto)): ?>
                    <?php foreach ($imagens_produto as $index => $imagem): $img_trim = trim($imagem); ?>
                        <div class="imagem-container <?= ($index == 0) ? 'principal' : '' ?>">
                            <img src="../assets/images/<?= htmlspecialchars($img_trim) ?>" class="img-thumbnail">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="manter_imagem[]" value="<?= htmlspecialchars($img_trim) ?>" id="img_<?= md5($img_trim) ?>" checked>
                                <label class="form-check-label" for="img_<?= md5($img_trim) ?>">Manter</label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="foto_principal" value="<?= htmlspecialchars($img_trim) ?>" id="principal_<?= md5($img_trim) ?>" <?= ($index == 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="principal_<?= md5($img_trim) ?>">Principal</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nenhuma imagem cadastrada.</p>
                <?php endif; ?>
            </div>
            
            <hr>

            <h5>Adicionar Novas Imagens</h5>
            <div class="mb-3">
                <label for="novas_imagens" class="form-label">Selecione uma ou mais imagens</label>
                <input class="form-control" type="file" name="novas_imagens[]" id="novas_imagens" multiple>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-4">Salvar Alterações</button>
</form>

<script>
    document.querySelectorAll('input[name="foto_principal"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.imagem-container').forEach(container => {
                container.classList.remove('principal');
            });
            if (this.checked) {
                this.closest('.imagem-container').classList.add('principal');
            }
        });
    });
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>