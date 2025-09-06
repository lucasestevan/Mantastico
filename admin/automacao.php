<?php include 'includes/header.php'; ?>

<h2>Automação de Cadastro por Lote (.ZIP)</h2>

<div class="card mt-4">
    <div class="card-body">
        <h5 class="card-title">Instruções de Uso</h5>
        <ol>
            <li>No seu computador, organize os produtos em pastas (o nome da pasta será o nome do produto).</li>
            <li>Crie um ou mais arquivos <strong>.ZIP</strong> contendo essas pastas.</li>
            <li>Defina os dados que serão aplicados a <strong>TODOS</strong> os produtos do(s) lote(s).</li>
            <li>Selecione um ou <strong>VÁRIOS</strong> arquivos .ZIP abaixo para iniciar o cadastro em fila.</li>
        </ol>

        <form id="upload-form" class="mt-4 border-top pt-4">
             <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="preco" class="form-label"><strong>1. Preço do Lote</strong></label>
                    <input type="number" step="0.01" class="form-control" name="preco" id="preco" value="299.90" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="categoria" class="form-label"><strong>2. Categoria do Lote</strong></label>
                    <input type="text" class="form-control" name="categoria" id="categoria" value="Geral" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="campeonato" class="form-label"><strong>3. Campeonato do Lote</strong></label>
                    <input type="text" class="form-control" name="campeonato" id="campeonato" placeholder="Ex: Brasileirão">
                </div>
            </div>
            <div class="mb-3">
                <label for="zip_files" class="form-label"><strong>4. Selecione um ou mais arquivos .ZIP</strong></label>
                <input class="form-control" type="file" name="zip_files[]" id="zip_files" accept=".zip" required multiple>
            </div>
            <button type="submit" class="btn btn-primary">Enviar e Iniciar Cadastro</button>
        </form>
    </div>
</div>

<div id="processing-section" class="card mt-4" style="display: none;">
    <div class="card-body">
        <h5 class="card-title" id="processing-title">Processando Lotes...</h5>
        <p id="status-text">Aguarde, não feche esta página.</p>
        <label>Progresso Geral:</label>
        <div class="progress mb-3" style="height: 25px;"><div id="progress-bar-geral" class="progress-bar bg-info" role="progressbar" style="width: 0%;">0%</div></div>
        <label>Progresso do Lote Atual:</label>
        <div class="progress" style="height: 25px;"><div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div></div>
        <div id="log-processamento" class="mt-3" style="font-family: monospace; font-size: 0.9em; max-height: 200px; overflow-y: auto; background-color: #f8f9fa; padding: 10px; border-radius: 5px;"></div>
    </div>
</div>

<a href="produtos.php" class="btn btn-secondary mt-3">Ir para a Lista de Produtos</a>

<script>
const uploadForm = document.getElementById('upload-form');
const processingSection = document.getElementById('processing-section');
const progressBar = document.getElementById('progress-bar');
const progressBarGeral = document.getElementById('progress-bar-geral');
const logDiv = document.getElementById('log-processamento');
const statusText = document.getElementById('status-text');
const processingTitle = document.getElementById('processing-title');
const zipInput = document.getElementById('zip_files');

uploadForm.addEventListener('submit', async function(event) {
    event.preventDefault();
    
    const button = this.querySelector('button');
    const preco = document.getElementById('preco').value;
    const categoria = document.getElementById('categoria').value;
    // Capturando o valor do campeonato
    const campeonato = document.getElementById('campeonato').value;
    const files = zipInput.files;

    if (files.length === 0) {
        alert('Por favor, selecione pelo menos um arquivo .ZIP.');
        return;
    }

    button.disabled = true;
    button.textContent = 'Processando...';
    processingSection.style.display = 'block';
    logDiv.innerHTML = "";

    let zipsProcessados = 0;
    const totalZips = files.length;

    for (const file of files) {
        zipsProcessados++;
        processingTitle.textContent = `Processando Lote ${zipsProcessados} de ${totalZips}: ${file.name}`;
        logDiv.innerHTML += `---<br><strong>Iniciando lote: ${file.name}</strong><br>`;

        const uploadFormData = new FormData();
        uploadFormData.append('zip_file', file);
        statusText.textContent = 'Enviando e descompactando arquivo...';
        
        const uploadResult = await fetch('upload_zip.php', {
            method: 'POST',
            body: uploadFormData
        }).then(res => res.json()).catch(err => ({ success: false, message: 'Erro de conexão no upload.'}));

        if (!uploadResult.success) {
            logDiv.innerHTML += `<span style="color: red;">Falha no upload do lote: ${uploadResult.message}</span><br>`;
            continue;
        }

        const pastasParaProcessar = uploadResult.pastas;
        const totalPastas = pastasParaProcessar.length;
        statusText.textContent = `Cadastrando ${totalPastas} produtos do lote: ${file.name}`;
        let pastasProcessadas = 0;

        for (const nomePasta of pastasParaProcessar) {
            logDiv.innerHTML += `Cadastrando: <strong>${nomePasta}</strong>... `;
            // Enviando o campeonato para o processador
            const processResult = await processarPasta(nomePasta, preco, categoria, campeonato);
            
            logDiv.innerHTML += processResult.success ? `<span style="color: green;">OK!</span><br>` : `<span style="color: red;">FALHA</span><br>`;
            
            pastasProcessadas++;
            const porcentagemLote = Math.round((pastasProcessadas / totalPastas) * 100);
            progressBar.style.width = porcentagemLote + '%';
            progressBar.textContent = porcentagemLote + '%';
        }

        const porcentagemGeral = Math.round((zipsProcessados / totalZips) * 100);
        progressBarGeral.style.width = porcentagemGeral + '%';
        progressBarGeral.textContent = porcentagemGeral + '%';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
    }

    processingTitle.textContent = 'Processamento Concluído!';
    statusText.textContent = `Todos os ${totalZips} lotes foram processados.`;
    progressBarGeral.classList.add('bg-success');
    button.disabled = false;
    button.textContent = 'Enviar e Iniciar Cadastro';
});

// Função que processa UMA pasta (trabalhador)
async function processarPasta(nomePasta, preco, categoria, campeonato) {
    const formData = new FormData();
    formData.append('pasta', nomePasta);
    formData.append('preco', preco);
    formData.append('categoria', categoria);
    formData.append('campeonato', campeonato); // Adicionando o campeonato

    try {
        const response = await fetch('processar_pasta.php', { method: 'POST', body: formData });
        return await response.json();
    } catch (error) {
        return { success: false, message: 'Erro de conexão.' };
    }
}
</script>

<?php include 'includes/footer.php'; ?>