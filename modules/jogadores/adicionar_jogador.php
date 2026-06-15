<?php
session_start();
require('../../config/db.php');
function h($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }

// --- LÓGICA DE EDIÇÃO REMOVIDA ---
// O ID é sempre 0 (novo jogador)
$id = 0;

// Carregar dados de apoio (posições, épocas)
$positions = $conn->query("SELECT id, code, name FROM positions ORDER BY FIELD(code,'GK','DF','MF','FW'), name")->fetch_all(MYSQLI_ASSOC);
$seasons   = $conn->query("SELECT id, nome FROM seasons ORDER BY data_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// Valores por defeito para um novo jogador
$player = [
  'primeiro_nome'=>'','ultimo_nome'=>'','data_nascimento'=>'',
  'altura_cm'=>'','peso_kg'=>'','pe_dominante'=>'D','position_id'=>null,'foto'=>'','ativo'=>1
];

// Buscar época atual (para o formulário de contrato)
$seasonAtual = $conn->query("SELECT id,nome FROM seasons WHERE data_inicio<=CURDATE() AND data_fim>=CURDATE() ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc()
  ?? $conn->query("SELECT id,nome FROM seasons ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc();

// --- LÓGICA DE EDIÇÃO REMOVIDA (Stats, Health, Contract) ---

// Valores por defeito para secções
$healthData = ['exam_date'=>'','health_status'=>'Apto','health_notes'=>''];
$contractData = ['start_date'=>'','end_date'=>'','wage_monthly'=>'','clauses'=>''];
$hasContract = false; // Contrato está desligado por defeito
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Novo Jogador - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/style.css">
</head>

<?php require('../../includes/sidebar.php'); ?>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="apple-container">
  <div class="form-column">
    <form id="mainPlayerForm" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_player">
      <input type="hidden" name="id" value="0">
      <input type="hidden" name="foto" id="fotoInput" value="">
      <input type="hidden" name="position_id" id="position_id_input" value="">

      <div class="unified-card">
        
        <div class="player-header-section">
          <div class="photo-section">
            <div class="photo-upload-card">
              <div class="photo-preview-wrapper">
                <img id="photoPreview" class="photo-preview" style="display: none;" src="" alt="Foto do Jogador">
                <div id="photoPlaceholder" class="photo-placeholder">
                  <i class="bi bi-person-circle"></i>
                  <p>Sem foto</p>
                </div>
              </div>
              
              <label class="photo-upload-label">
                <i class="bi bi-cloud-upload"></i>
                Carregar Foto
                <input type="file" id="photoInput" accept="image/*" onchange="previewPhoto(this)">
              </label>
              
              <div class="photo-info">
                📸 JPG, PNG, GIF<br>
                💾 Máx: 5MB
              </div>
            </div>
          </div>

          <div class="header-info-section">
            <div class="header-actions-row">
              <div class="header-text-content">
                <h1 class="header-title">
                  Novo Jogador
                </h1>
                <p class="header-subtitle">
                  Preencha todos os dados do novo jogador
                </p>
              </div>

              <div class="header-buttons-group">
                <a href="listar.php" class="back-btn">
                  <i class="bi bi-arrow-left"></i>
                  Voltar
                </a>
                
                </div>
            </div>

            <div class="header-badges">
              <div class="header-badge">
              </div>
              <div class="header-badge">
              </div>
              
              <div class="header-badge">
              </div>
              
            </div>
          </div>
        </div>
        
        <div class="section-divider">
          <i class="bi bi-person-circle"></i>
          <h2>Dados Pessoais</h2>
        </div>

        <div class="row">
          <div class="col-6">
            <label class="form-label">Primeiro Nome</label>
            <input name="primeiro_nome" class="apple-input" required value="" placeholder="Ex: Cristiano">
          </div>
          <div class="col-6">
            <label class="form-label">Último Nome</label>
            <input name="ultimo_nome" class="apple-input" required value="" placeholder="Ex: Ronaldo">
          </div>

          <div class="col-4">
            <label class="form-label">Data de Nascimento</label>
            <input type="date" name="data_nascimento" class="apple-input" required value="">
          </div>
          <div class="col-4">
            <label class="form-label">Altura (cm)</label>
            <input type="number" name="altura_cm" class="apple-input" value="" placeholder="185">
          </div>
          <div class="col-4">
            <label class="form-label">Peso (kg)</label>
            <input type="number" name="peso_kg" class="apple-input" value="" placeholder="80">
          </div>

          <div class="col-6">
            <label class="form-label">Pé Dominante</label>
            <select name="pe_dominante" class="apple-select">
              <option value="D" selected>Direito</option>
              <option value="E">Esquerdo</option>
              <option value="Ambos">Ambos</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Status</label>
            <select name="ativo" class="apple-select">
              <option value="1" selected>✅ Ativo</option>
              <option value="0">⛔ Inativo</option>
            </select>
          </div>
        </div>

        <div class="section-divider">
          <i class="bi bi-bullseye"></i>
          <h2>Posição em Campo</h2>
        </div>

        <div class="football-pitch-container">
          <div class="pitch-label-header">
            <label class="form-label">
              <i class="bi bi-geo-alt-fill"></i> Selecione a Posição do Jogador
            </label>
          </div>

          <div class="pitch-horizontal">
            <div class="pitch-column gk">
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_gk" value="GK" onchange="updatePosition('GK', 'Guarda-Redes')">
                <label for="pos_gk" class="position-badge"><div class="position-code">GK</div></label>
              </div>
            </div>

            <div class="pitch-column defense">
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_lb" value="LB" onchange="updatePosition('LB', 'Lateral Esquerdo')">
                <label for="pos_lb" class="position-badge"><div class="position-code">LB</div></label>
              </div>
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_cb1" value="CB" onchange="updatePosition('CB', 'Defesa Central')">
                <label for="pos_cb1" class="position-badge"><div class="position-code">CB</div></label>
              </div>
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_cb2" value="CB" onchange="updatePosition('CB', 'Defesa Central')">
                <label for="pos_cb2" class="position-badge"><div class="position-code">CB</div></label>
              </div>
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_rb" value="RB" onchange="updatePosition('RB', 'Lateral Direito')">
                <label for="pos_rb" class="position-badge"><div class="position-code">RB</div></label>
              </div>
            </div>

            <div class="pitch-column">
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_cdm" value="CDM" onchange="updatePosition('CDM', 'Médio Defensivo')">
                <label for="pos_cdm" class="position-badge"><div class="position-code">CDM</div></label>
              </div>
            </div>

            <div class="pitch-column" style="position: relative;">
              <div class="position-side-group">
                <div class="position-slot">
                  <input type="radio" name="position_radio" id="pos_lm" value="LM" onchange="updatePosition('LM', 'Médio Esquerdo')">
                  <label for="pos_lm" class="position-badge"><div class="position-code">LM</div></label>
                </div>
                <div class="position-slot">
                  <input type="radio" name="position_radio" id="pos_rm" value="RM" onchange="updatePosition('RM', 'Médio Direito')">
                  <label for="pos_rm" class="position-badge"><div class="position-code">RM</div></label>
                </div>
              </div>
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_cm" value="CM" onchange="updatePosition('CM', 'Médio Centro')">
                <label for="pos_cm" class="position-badge"><div class="position-code">CM</div></label>
              </div>
            </div>

            <div class="pitch-column">
              <div class="position-slot" style="margin-bottom: 30px;">
                <input type="radio" name="position_radio" id="pos_cam" value="CAM" onchange="updatePosition('CAM', 'Médio Ofensivo')">
                <label for="pos_cam" class="position-badge"><div class="position-code">CAM</div></label>
              </div>
              <div class="position-slot">
                <input type="radio" name="position_radio" id="pos_cf" value="CF" onchange="updatePosition('CF', 'Avançado Centro')">
                <label for="pos_cf" class="position-badge"><div class="position-code">CF</div></label>
              </div>
            </div>

            <div class="pitch-column">
              <div class="position-wing-group">
                <div class="position-slot">
                  <input type="radio" name="position_radio" id="pos_lw" value="LW" onchange="updatePosition('LW', 'Extremo Esquerdo')">
                  <label for="pos_lw" class="position-badge"><div class="position-code">LW</div></label>
                </div>
                <div class="position-slot">
                  <input type="radio" name="position_radio" id="pos_rw" value="RW" onchange="updatePosition('RW', 'Extremo Direito')">
                  <label for="pos_rw" class="position-badge"><div class="position-code">RW</div></label>
                </div>
              </div>
              <div class="position-slot" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <input type="radio" name="position_radio" id="pos_st" value="ST" onchange="updatePosition('ST', 'Avançado')">
                <label for="pos_st" class="position-badge"><div class="position-code">ST</div></label>
              </div>
            </div>
          </div>

          <div class="selected-position-display">
            <div class="label">Posição Selecionada</div>
            <div class="value" id="selectedPositionDisplay">
              Nenhuma posição selecionada
            </div>
          </div>
        </div>

        <div class="section-divider">
          <i class="bi bi-heart-pulse"></i>
          <h2>Registo Médico</h2>
        </div>

        <div class="row">
          <div class="col-6">
            <label class="form-label">Data do Exame</label>
            <input type="date" name="exam_date" class="apple-input" value="">
          </div>
          <div class="col-6">
            <label class="form-label">Estado de Saúde</label>
            <select name="health_status" class="apple-select">
              <option value="Apto" selected>✅ Apto</option>
              <option value="Condicionado">⚠️ Condicionado</option>
              <option value="Inapto">❌ Inapto</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Notas e Observações Médicas</label>
            <textarea name="health_notes" rows="3" class="apple-input" style="resize: vertical;" placeholder="Adicione observações médicas relevantes..."></textarea>
          </div>
        </div>

        <div class="contract-toggle-section">
          <div class="contract-toggle-header">
            <div class="contract-toggle-title">
              <i class="bi bi-file-earmark-text-fill"></i>
              <h3>Informações de Contrato</h3>
            </div>
            
            <div class="contract-switch-wrapper">
              <span class="contract-switch-label">Adicionar Contrato</span>
              <label class="toggle-switch">
                <input type="checkbox" id="contractToggle" onchange="toggleContractForm()">
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>

          <div class="contract-form-area" id="contractFormArea">
            <div class="contract-info-box">
              <i class="bi bi-info-circle-fill"></i>
              <div class="contract-info-text">
                <strong>💼 Informações do Contrato</strong>
                Preencha os dados do contrato do jogador. Todos os campos são opcionais, mas recomendamos preencher as datas de início e fim para melhor controlo.
              </div>
            </div>

            <div class="row">
              <div class="col-6">
                <label class="form-label">Data de Início</label>
                <input type="date" name="contract_start_date" class="apple-input" value="">
              </div>
              <div class="col-6">
                <label class="form-label">Data de Fim</label>
                <input type="date" name="contract_end_date" class="apple-input" value="">
              </div>
              <div class="col-6">
                <label class="form-label">Salário Mensal (€)</label>
                <input type="number" step="0.01" name="wage_monthly" class="apple-input" value="" placeholder="Ex: 5000.00">
              </div>
              <div class="col-6">
                <label class="form-label">Época do Contrato</label>
                <select name="contract_season_id" class="apple-select">
                  <option value="">Selecione a época</option>
                  <?php foreach($seasons as $season): ?>
                    <option value="<?= (int)$season['id'] ?>" <?= $seasonAtual && $seasonAtual['id']==$season['id']?'selected':'' ?>>
                      <?= h($season['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Cláusulas e Observações</label>
                <textarea name="contract_clauses" rows="3" class="apple-input" style="resize: vertical;" placeholder="Adicione cláusulas especiais, bónus, ou outras observações sobre o contrato..."></textarea>
              </div>
            </div>
          </div>
        </div>
                    
        <button type="submit" class="main-save-btn">
          <i class="bi bi-check-circle-fill"></i>
          Criar Jogador
        </button>
      </div>
    </form>
  </div>
</div>

<script>
console.log('🔍 DEBUG - Script carregado (Modo Adicionar)');

// Funções auxiliares primeiro
function previewPhoto(input) {
  const preview = document.getElementById('photoPreview');
  const placeholder = document.getElementById('photoPlaceholder');
  
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validar tamanho (máx 5MB)
    if (file.size > 5 * 1024 * 1024) {
      showToast('error', 'Erro!', 'A imagem não pode exceder 5MB');
      return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = new Image();
      img.onload = function() {
        // Redimensionar para máximo 800px (mantém proporção)
        const MAX_WIDTH = 800;
        const MAX_HEIGHT = 800;
        
        let width = img.width;
        let height = img.height;
        
        if (width > height) {
          if (width > MAX_WIDTH) {
            height *= MAX_WIDTH / width;
            width = MAX_WIDTH;
          }
        } else {
          if (height > MAX_HEIGHT) {
            width *= MAX_HEIGHT / height;
            height = MAX_HEIGHT;
          }
        }
        
        // Criar canvas para redimensionar
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        
        // Converter para Base64 (JPEG com 85% qualidade)
        const resizedBase64 = canvas.toDataURL('image/jpeg', 0.85);
        
        // Atualizar preview e input hidden
        preview.src = resizedBase64;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        document.getElementById('fotoInput').value = resizedBase64;
        
        console.log('📸 Imagem redimensionada:', 
          `Original: ${img.width}x${img.height}`,
          `Nova: ${width}x${height}`,
          `Tamanho Base64: ${(resizedBase64.length / 1024).toFixed(2)}KB`
        );
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
} {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
      placeholder.style.display = 'none';
      document.getElementById('fotoInput').value = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function updatePosition(code, name) {
  console.log('📍 Posição atualizada:', code, name);
  document.getElementById('position_id_input').value = code;
  document.getElementById('selectedPositionDisplay').textContent = code + ' - ' + name;
}

function toggleContractForm() {
  const checkbox = document.getElementById('contractToggle');
  const formArea = document.getElementById('contractFormArea');
  
  if (checkbox.checked) {
    formArea.classList.add('active');
  } else {
    formArea.classList.remove('active');
  }
}

function showToast(type, title, message) {
  const container = document.getElementById('toastContainer');
  
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  const icons = {
    success: 'bi-check-circle-fill',
    error: 'bi-x-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill'
  };
  
  toast.innerHTML = `
    <i class="toast-icon bi ${icons[type]}"></i>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button class="toast-close" onclick="this.parentElement.remove()">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  
  container.appendChild(toast);
  
  setTimeout(() => {
    toast.remove();
  }, 5000);
}

// --- Função de Eliminar removida ---

// Inicialização quando o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
  console.log('✅ DOM Carregado');
  
  const form = document.getElementById('mainPlayerForm');
  console.log('📋 Formulário encontrado:', form ? 'SIM' : 'NÃO');
  
  if (!form) {
    console.error('❌ ERRO: Formulário não encontrado!');
    return;
  }
  
  const submitBtn = form.querySelector('button[type="submit"]');
  console.log('🔘 Botão submit encontrado:', submitBtn ? 'SIM' : 'NÃO');
  
  // Evento de submit
  form.addEventListener('submit', function(e) {
    console.log('🎯 Formulário submetido!');
    e.preventDefault();
    
    // Validar posição
    const positionInput = document.getElementById('position_id_input');
    console.log('📍 Posição selecionada:', positionInput ? positionInput.value : 'Campo não encontrado');
    
    if (!positionInput || !positionInput.value) {
      console.warn('⚠️ Nenhuma posição selecionada');
      showToast('warning', 'Atenção!', 'Por favor, selecione uma posição para o jogador');
      return;
    }
    
    const formData = new FormData(this);
    
    // Log de todos os dados
    console.log('📦 Dados a enviar:');
    for (let [key, value] of formData.entries()) {
      console.log(`  ${key}:`, value);
    }
    
    // Adicionar contrato
    const contractToggle = document.getElementById('contractToggle');
    formData.append('has_contract', contractToggle && contractToggle.checked ? '1' : '0');
    
    // Loading no botão
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
    }
    
    console.log('🚀 Enviando para processar.php...');
    
    fetch('processar.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      console.log('📥 Resposta recebida:', response.status, response.statusText);
      
      const contentType = response.headers.get('content-type');
      console.log('📄 Content-Type:', contentType);
      
      if (!contentType || !contentType.includes('application/json')) {
        return response.text().then(text => {
          console.error('❌ Resposta NÃO é JSON:', text.substring(0, 500));
          throw new Error('Resposta do servidor não é JSON. Verifica processar.php');
        });
      }
      
      return response.json();
    })
    .then(data => {
      console.log('✅ Dados recebidos:', data);
      
      if (data.success) {
        showToast('success', 'Sucesso!', data.message);
        
        setTimeout(() => {
          console.log('🔄 Redirecionando para listar.php...');
          // Redireciona sempre para a lista após criar
          window.location.href = 'listar.php';
        }, 1500);
      } else {
        console.error('❌ Erro retornado:', data.message);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Criar Jogador';
        }
        showToast('error', 'Erro!', data.message || 'Erro ao guardar jogador');
      }
    })
    .catch(error => {
      console.error('💥 ERRO CRÍTICO:', error);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Criar Jogador';
      }
      showToast('error', 'Erro!', 'Erro: ' + error.message);
    });
  });
  
  // --- Bloco de inicialização de posição removido ---
});
</script>

</body>
</html>