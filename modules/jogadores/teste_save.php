<?php
session_start();
require('../../config/db.php');

// Teste simples de criação de jogador
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Criação - SportGes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">🧪 Teste de Criação de Jogador</h3>
        </div>
        <div class="card-body">
            <form id="testForm">
                <div class="mb-3">
                    <label class="form-label">Primeiro Nome</label>
                    <input type="text" name="primeiro_nome" class="form-control" value="João" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Último Nome</label>
                    <input type="text" name="ultimo_nome" class="form-control" value="Silva" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" class="form-control" value="2000-01-01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Posição</label>
                    <select name="position_id" class="form-select">
                        <option value="">Nenhuma</option>
                        <option value="ST">ST - Avançado</option>
                        <option value="CM">CM - Médio Centro</option>
                        <option value="GK">GK - Guarda-Redes</option>
                    </select>
                </div>
                
                <input type="hidden" name="action" value="save_player">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="pe_dominante" value="D">
                <input type="hidden" name="ativo" value="1">
                
                <button type="submit" class="btn btn-success btn-lg w-100">
                    ✅ Criar Jogador de Teste
                </button>
            </form>
            
            <div id="resultado" class="mt-4" style="display: none;">
                <div class="alert" id="alertBox"></div>
                <pre id="responseData" class="bg-dark text-white p-3" style="border-radius: 8px;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('testForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const resultadoDiv = document.getElementById('resultado');
    const alertBox = document.getElementById('alertBox');
    const responseData = document.getElementById('responseData');
    
    console.log('📤 Enviando dados...');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    fetch('processar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📥 Resposta recebida:', response);
        return response.text();
    })
    .then(text => {
        console.log('📄 Texto da resposta:', text);
        
        resultadoDiv.style.display = 'block';
        responseData.textContent = text;
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                alertBox.className = 'alert alert-success';
                alertBox.innerHTML = `
                    <strong>✅ Sucesso!</strong><br>
                    ${data.message}<br>
                    <small>ID do jogador: ${data.player_id || 'N/A'}</small>
                `;
                
                console.log('✅ Jogador criado com ID:', data.player_id);
            } else {
                alertBox.className = 'alert alert-danger';
                alertBox.innerHTML = `
                    <strong>❌ Erro!</strong><br>
                    ${data.message}
                `;
                
                console.error('❌ Erro:', data.message);
            }
        } catch (e) {
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = `
                <strong>❌ Erro ao processar resposta!</strong><br>
                A resposta não é JSON válido. Veja os detalhes abaixo.
            `;
            console.error('❌ Erro ao parsear JSON:', e);
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        
        resultadoDiv.style.display = 'block';
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = `
            <strong>❌ Erro de Rede!</strong><br>
            ${error.message}
        `;
        responseData.textContent = error.toString();
    });
});
</script>
</body>
</html>