<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

require('../../config/db.php');

$user_id       = $_SESSION['user_id'];
$user_role     = $_SESSION['user_role'] ?? 0;
$user_club_id  = $_SESSION['club_id'] ?? 0;

// Só o user_id = 7 é SuperAdmin
$isSuperAdmin = ($user_id == 7);

if ($isSuperAdmin) {
  // SuperAdmin vê todas as equipas
  $teams_result = $conn->query("
    SELECT t.id, CONCAT(c.nome, ' - ', t.escaloes) AS nome_completo
    FROM teams t
    INNER JOIN clubs c ON t.club_id = c.id
    ORDER BY c.nome, t.escaloes
  ");
} else {
  // Administradores normais só vêem equipas do seu próprio clube
  $stmtTeams = $conn->prepare("
    SELECT t.id, CONCAT(c.nome, ' - ', t.escaloes) AS nome_completo
    FROM teams t
    INNER JOIN clubs c ON t.club_id = c.id
    WHERE t.club_id = ?
    ORDER BY t.escaloes
  ");
  $stmtTeams->bind_param("i", $user_club_id);
  $stmtTeams->execute();
  $teams_result = $stmtTeams->get_result();
}

$teams = [];
while ($row = $teams_result->fetch_assoc()) {
  $teams[] = $row;
}




// Verificar se existem equipas
if (empty($teams)) {
  $_SESSION['error'] = 'Nenhuma equipa encontrada. Por favor, crie uma equipa primeiro.';
  // Não redireciona, mas mostra aviso
}

// 🔥 CARREGAR TEMPORADAS COMO ARRAY
$seasons_result = $conn->query("SELECT * FROM seasons ORDER BY data_inicio DESC");

$current_season = null;
$seasons_list = [];
while($season = $seasons_result->fetch_assoc()) {
  $seasons_list[] = $season;
  if (!$current_season) {
    $current_season = $season['id'];
  }
}

// Verificar se existem temporadas
if (empty($seasons_list)) {
  $_SESSION['error'] = 'Nenhuma temporada encontrada. Por favor, crie uma temporada primeiro.';
}

$jogo = null;
if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $result = $conn->query("SELECT * FROM matches WHERE id=$id");
  $jogo = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $team_id = intval($_POST['team_id']);
  $season_id = intval($_POST['season_id']);
  $adversario = $conn->real_escape_string($_POST['adversario']);
  $data_jogo = $conn->real_escape_string($_POST['data_jogo']);
  $local = $conn->real_escape_string($_POST['local']);
  $estado = $conn->real_escape_string($_POST['estado']);
  $golos_marcados = intval($_POST['golos_marcados']);
  $golos_sofridos = intval($_POST['golos_sofridos']);
  
  if ($jogo) {
    $sql = "UPDATE matches SET team_id=$team_id, season_id=$season_id, adversario='$adversario', data_jogo='$data_jogo', 
            local='$local', estado='$estado', golos_marcados=$golos_marcados, golos_sofridos=$golos_sofridos 
            WHERE id=" . $jogo['id'];
  } else {
    $sql = "INSERT INTO matches (team_id, season_id, adversario, data_jogo, local, estado, golos_marcados, golos_sofridos) 
            VALUES ($team_id, $season_id, '$adversario', '$data_jogo', '$local', '$estado', $golos_marcados, $golos_sofridos)";
  }
  
  if ($conn->query($sql)) {
    $_SESSION['success'] = $jogo ? 'Jogo atualizado com sucesso!' : 'Jogo criado com sucesso!';
    header("Location: listar.php");
    exit;
  } else {
    $erro = "Erro ao guardar: " . $conn->error;
  }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $jogo ? 'Editar' : 'Novo' ?> Jogo - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f8fafc;
      color: #1e293b;
      min-height: 100vh;
    }

    .main-content {
      margin-left: 240px;
      padding: 2rem;
      width: calc(100% - 240px);
      min-height: 100vh;
    }

    .content-wrapper {
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .page-header-left h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 0.5rem 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .page-header-left h1 i {
      font-size: 2.5rem;
      color: #3b82f6;
    }

    .page-header-left p {
      color: #64748b;
      margin: 0;
      font-size: 1rem;
    }

    .page-header-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .btn-header {
      padding: 0.875rem 1.75rem;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s;
      text-decoration: none;
      white-space: nowrap;
    }

    .btn-back {
      background: #f1f5f9;
      color: #475569;
    }

    .btn-back:hover {
      background: #e2e8f0;
      color: #334155;
      transform: translateY(-2px);
    }

    .form-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      overflow: hidden;
    }

    .form-section {
      padding: 2.5rem 2rem;
      border-bottom: 1px solid #f1f5f9;
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f1f5f9;
    }

    .section-header i {
      font-size: 1.75rem;
      color: #3b82f6;
    }

    .section-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      flex: 1;
    }

    .form-label {
      display: block;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #334155;
      margin-bottom: 0.75rem;
    }

    .label-badge {
      display: inline-block;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      font-size: 0.75rem;
      padding: 0.25rem 0.625rem;
      border-radius: 6px;
      margin-left: 0.5rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .apple-input,
    .apple-select {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1rem;
      color: #1e293b;
      background: white;
      transition: all 0.2s;
    }

    .apple-input:focus,
    .apple-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .apple-input::placeholder {
      color: #94a3b8;
    }

    .apple-select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      padding-right: 3rem;
    }

    .submit-section {
      padding: 2.5rem 2rem;
      background: #f8fafc;
      text-align: center;
    }

    .main-save-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      padding: 1.125rem 3.5rem;
      border-radius: 12px;
      font-size: 1.0625rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.625rem;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .main-save-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .main-save-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .alert {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.9375rem;
      font-weight: 500;
    }

    .alert-error {
      background: #fee2e2;
      color: #dc2626;
      border-left: 4px solid #dc2626;
    }

    /* Responsive */
    @media (max-width: 1023px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1.5rem;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .page-header {
        padding: 1.5rem;
        flex-direction: column;
        align-items: stretch;
      }

      .page-header-left h1 {
        font-size: 1.5rem;
      }

      .page-header-left h1 i {
        font-size: 1.75rem;
      }

      .page-header-left p {
        font-size: 0.9rem;
      }

      .page-header-actions {
        width: 100%;
      }

      .btn-header {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1.5rem;
      }

      .form-section {
        padding: 1.5rem 1rem;
      }

      .section-header {
        margin-bottom: 1.5rem;
      }

      .section-header h2 {
        font-size: 1.25rem;
      }

      .section-header i {
        font-size: 1.5rem;
      }

      .submit-section {
        padding: 1.5rem 1rem;
      }

      .main-save-btn {
        width: 100%;
        justify-content: center;
        padding: 1rem 2rem;
      }

      /* Fix para input datetime-local em mobile */
      .apple-input[type="datetime-local"] {
        font-size: 16px;
        min-height: 48px;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
      }

      /* Ajuste para todos os inputs em mobile */
      .apple-input,
      .apple-select {
        font-size: 16px;
        min-height: 48px;
        padding: 0.75rem 1rem;
      }

      /* Melhor espaçamento entre labels e inputs */
      .form-label {
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
      }

      /* Ajuste do badge no label */
      .label-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
      }
    }

    @media (max-width: 480px) {
      .page-header {
        padding: 1rem;
      }

      .page-header-left h1 {
        font-size: 1.25rem;
      }

      .form-section {
        padding: 1.25rem 0.75rem;
      }

      .section-header {
        flex-wrap: wrap;
      }

      .section-header h2 {
        font-size: 1.125rem;
        width: 100%;
      }

      /* Inputs ainda maiores em telas muito pequenas */
      .apple-input,
      .apple-select {
        min-height: 52px;
        padding: 0.875rem 1rem;
      }

      .apple-input[type="datetime-local"] {
        min-height: 52px;
      }

      .main-save-btn {
        font-size: 1rem;
      }

      .alert {
        font-size: 0.875rem;
        padding: 0.875rem 1rem;
      }
    }
  </style>
</head>

<body>
<?php require('../../includes/sidebar.php'); ?>

  <div class="main-content">
    <div class="content-wrapper">
      
      <div class="page-header">
        <div class="page-header-left">
          <h1>
            <i class='bx bx-football'></i>
            <?= $jogo ? 'Editar Jogo' : 'Novo Jogo' ?>
          </h1>
          <p><?= $jogo ? 'Atualize as informações do jogo' : 'Preencha todos os dados do novo jogo' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class='bx bx-arrow-back'></i>
            Voltar
          </a>
        </div>
      </div>

      <div class="form-container">
        <?php if (isset($erro)): ?>
          <div class="alert alert-error">
            <i class='bx bx-error-circle'></i>
            <?= $erro ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="matchForm">
          
          <div class="form-section">
            <div class="section-header">
              <i class='bx bx-football'></i>
              <h2>Informações da Partida</h2>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">
                  Temporada *
                  <span class="label-badge">Atual</span>
                </label>
                <select name="season_id" class="apple-select" required>
                  <option value="">Selecione a temporada...</option>
                  <?php foreach($seasons_list as $index => $season): 
                    $ano_inicio = date('Y', strtotime($season['data_inicio']));
                    $ano_fim = date('Y', strtotime($season['data_fim']));
                  ?>
                    <option value="<?= $season['id'] ?>" 
                      <?php 
                        if ($jogo) {
                          echo ($jogo['season_id'] == $season['id']) ? 'selected' : '';
                        } else {
                          echo ($index === 0) ? 'selected' : '';
                        }
                      ?>
                    >
                      <?= htmlspecialchars($season['nome']) ?> (<?= $ano_inicio ?>/<?= $ano_fim ?>)
                      <?= ($index === 0) ? ' - Atual' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
  <label class="form-label">Equipa *</label>
  <select name="team_id" class="apple-select" required>
    <option value="">Selecione a equipa...</option>
    <?php 
    if (empty($teams)) {
      echo '<option value="" disabled>Nenhuma equipa disponível</option>';
    } else {
      foreach($teams as $t): 
    ?>
      <option value="<?= $t['id'] ?>" <?= ($jogo && $jogo['team_id'] == $t['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($t['nome_completo']) ?>
      </option>
    <?php 
      endforeach;
    }
    ?>
  </select>
</div>

              <div class="col-md-6">
                <label class="form-label">Adversário *</label>
                <input 
                  type="text" 
                  name="adversario" 
                  class="apple-input" 
                  value="<?= $jogo ? htmlspecialchars($jogo['adversario']) : '' ?>" 
                  placeholder="Ex: Sporting CP"
                  required
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Data e Hora *</label>
                <input 
                  type="datetime-local" 
                  name="data_jogo" 
                  class="apple-input" 
                  value="<?= $jogo ? date('Y-m-d\TH:i', strtotime($jogo['data_jogo'])) : '' ?>" 
                  required
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Local *</label>
                <select name="local" class="apple-select" required>
                  <option value="">Selecione o local</option>
                  <option value="Casa" <?= ($jogo && $jogo['local'] == 'Casa') ? 'selected' : '' ?>>Casa</option>
                  <option value="Fora" <?= ($jogo && $jogo['local'] == 'Fora') ? 'selected' : '' ?>>Fora</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Estado *</label>
                <select name="estado" class="apple-select" required>
                  <option value="Agendado" <?= ($jogo && $jogo['estado'] == 'Agendado') ? 'selected' : '' ?>>Agendado</option>
                  <option value="Decorrido" <?= ($jogo && $jogo['estado'] == 'Decorrido') ? 'selected' : '' ?>>Decorrido</option>
                  <option value="Concluido" <?= ($jogo && $jogo['estado'] == 'Concluido') ? 'selected' : '' ?>>Concluído</option>
                  <option value="Adiado" <?= ($jogo && $jogo['estado'] == 'Adiado') ? 'selected' : '' ?>>Adiado</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="section-header">
              <i class='bx bx-target-lock'></i>
              <h2>Resultado</h2>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Golos Marcados</label>
                <input 
                  type="number" 
                  name="golos_marcados" 
                  class="apple-input" 
                  min="0" 
                  value="<?= $jogo ? $jogo['golos_marcados'] : 0 ?>"
                  placeholder="0"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Golos Sofridos</label>
                <input 
                  type="number" 
                  name="golos_sofridos" 
                  class="apple-input" 
                  min="0" 
                  value="<?= $jogo ? $jogo['golos_sofridos'] : 0 ?>"
                  placeholder="0"
                >
              </div>
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class='bx bx-check-circle'></i>
              <?= $jogo ? 'Atualizar Jogo' : 'Criar Jogo' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../toast.js"></script>
  <script>
  document.getElementById('matchForm').addEventListener('submit', function(e) {
    const seasonId = this.querySelector('[name="season_id"]').value;
    const teamId = this.querySelector('[name="team_id"]').value;
    const adversario = this.querySelector('[name="adversario"]').value.trim();
    const dataJogo = this.querySelector('[name="data_jogo"]').value;
    const local = this.querySelector('[name="local"]').value;
    
    if (!seasonId) {
      e.preventDefault();
      showToast('warning', 'Atenção!', 'Selecione uma temporada');
      return false;
    }
    
    if (!teamId) {
      e.preventDefault();
      showToast('warning', 'Atenção!', 'Selecione uma equipa');
      return false;
    }
    
    if (adversario.length < 3) {
      e.preventDefault();
      showToast('warning', 'Atenção!', 'O nome do adversário deve ter pelo menos 3 caracteres');
      return false;
    }
    
    if (!dataJogo) {
      e.preventDefault();
      showToast('warning', 'Atenção!', 'Selecione a data e hora do jogo');
      return false;
    }
    
    if (!local) {
      e.preventDefault();
      showToast('warning', 'Atenção!', 'Selecione o local do jogo');
      return false;
    }
  });
  </script>
</body>
</html>