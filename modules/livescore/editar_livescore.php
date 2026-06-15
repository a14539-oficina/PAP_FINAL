<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require('../../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: livescore.php");
    exit;
}

$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Buscar dados do jogo
$stmt = $conn->prepare("
    SELECT m.*, 
           t.nome as team_nome, 
           t.escaloes,
           t.id as team_id,
           c.nome as clube_nome
    FROM matches m
    JOIN teams t ON t.id = m.team_id
    JOIN clubs c ON c.id = t.club_id
    WHERE m.id = ?
");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if (!$match) {
    die("Jogo não encontrado");
}

// Buscar jogadores da equipa
$jogadoresStmt = $conn->prepare("
    SELECT p.id, 
           CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as nome
    FROM players p
    WHERE p.team_id = ?
    ORDER BY p.primeiro_nome ASC, p.ultimo_nome ASC
");
$jogadoresStmt->bind_param("i", $match['team_id']);
$jogadoresStmt->execute();
$jogadores = $jogadoresStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Descobrir o nome correto da coluna do jogador
$columnCheck = $conn->query("SHOW COLUMNS FROM match_goals LIKE '%jogador%'");
$jogadorColumn = 'jogador'; // padrão
if ($columnCheck && $columnCheck->num_rows > 0) {
    $col = $columnCheck->fetch_assoc();
    $jogadorColumn = $col['Field'];
}

// Buscar golos registados usando o nome correto da coluna
$golosStmt = $conn->prepare("
    SELECT id, match_id, equipa, $jogadorColumn as jogador_nome, minuto, created_at 
    FROM match_goals 
    WHERE match_id = ? 
    ORDER BY minuto ASC, created_at ASC
");
$golosStmt->bind_param("i", $match_id);
$golosStmt->execute();
$golos = $golosStmt->get_result()->fetch_all(MYSQLI_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Live Score - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f8fafc;
      color: #1e293b;
    }

    .main-content {
      margin-left: 240px;
      padding: 2rem;
      width: calc(100% - 240px);
      min-height: 100vh;
    }

    .content-wrapper {
      max-width: 1000px;
      margin: 0 auto;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: #64748b;
      text-decoration: none;
      margin-bottom: 1.5rem;
      font-weight: 600;
      transition: color 0.2s;
    }

    .back-link:hover {
      color: #3b82f6;
    }

    .match-header-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      border-left: 4px solid #ef4444;
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .live-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      padding: 0.625rem 1.25rem;
      border-radius: 24px;
      font-size: 0.875rem;
      font-weight: 700;
      animation: pulse 2s infinite;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .header-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .btn-display, .btn-finalizar-top {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.875rem 1.75rem;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9375rem;
      text-decoration: none;
    }

    .btn-display {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
    }

    .btn-display:hover {
      background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(139, 92, 246, 0.4);
    }

    .btn-finalizar-top {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-finalizar-top:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
    }

    .btn-finalizar-top:active {
      transform: translateY(-1px);
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }

    .live-dot {
      width: 8px;
      height: 8px;
      background: white;
      border-radius: 50%;
      animation: blink 1s infinite;
    }

    @keyframes blink {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.2; }
    }

    .match-display {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 2.5rem;
      margin-bottom: 1.5rem;
    }

    .score-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e2e8f0;
    }

    .btn-golo-left {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 1.25rem 2rem;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border: none;
      border-radius: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 1rem;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
    }

    .btn-golo-left:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    }

    .btn-golo-left:active {
      transform: translateY(-1px);
    }

    .btn-golo-right {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 1.25rem 2rem;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      border-radius: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 1rem;
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
    }

    .btn-golo-right:hover {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    }

    .btn-golo-right:active {
      transform: translateY(-1px);
    }

    .team-display {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .team-display.home {
      align-items: flex-end;
    }

    .team-display-name {
      font-size: 2rem;
      font-weight: 900;
      color: #0f172a;
      line-height: 1.2;
    }

    .team-display-info {
      font-size: 0.9375rem;
      color: #64748b;
      font-weight: 500;
    }

    .score-display {
      font-size: 5rem;
      font-weight: 900;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      letter-spacing: 4px;
      text-align: center;
      line-height: 1;
    }

    .score-separator {
      color: #cbd5e1;
      margin: 0 0.5rem;
    }

    .match-info-row {
      display: flex;
      justify-content: center;
      gap: 3rem;
      font-size: 0.9375rem;
      color: #64748b;
      padding: 1.5rem 0;
      border-top: 2px solid #e2e8f0;
      border-bottom: 2px solid #e2e8f0;
      font-weight: 500;
    }

    .match-info-item {
      display: flex;
      align-items: center;
      gap: 0.625rem;
    }

    .match-info-item i {
      font-size: 1.125rem;
      color: #94a3b8;
    }

    .timeline-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .timeline-card h2 {
      font-size: 1.25rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .timeline {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .timeline-item {
      display: grid;
      grid-template-columns: 60px 1fr auto;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: #f8fafc;
      border-radius: 12px;
      border-left: 4px solid #10b981;
      transition: all 0.2s;
    }

    .timeline-item:hover {
      background: #f1f5f9;
    }

    .timeline-item.adversario {
      border-left-color: #3b82f6;
    }

    .timeline-minuto {
      font-size: 1.25rem;
      font-weight: 800;
      color: #0f172a;
      text-align: center;
    }

    .timeline-info {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .timeline-jogador {
      font-weight: 700;
      color: #0f172a;
    }

    .timeline-equipa {
      font-size: 0.875rem;
      color: #64748b;
    }

    .timeline-actions {
      display: flex;
      gap: 0.5rem;
    }

    .btn-delete-golo {
      background: #fee2e2;
      color: #dc2626;
      border: none;
      padding: 0.5rem;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      transition: all 0.2s;
    }

    .btn-delete-golo:hover {
      background: #fecaca;
    }

    .btn-delete-golo:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .empty-timeline {
      text-align: center;
      padding: 3rem;
      color: #94a3b8;
    }

    .empty-timeline i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.3;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.6);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .modal.active {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      max-width: 700px;
      width: 100%;
      padding: 2rem;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .modal-header h3 {
      font-size: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .modal-header .team-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .modal-header .badge-casa {
      background: #d1fae5;
      color: #065f46;
    }

    .modal-header .badge-fora {
      background: #dbeafe;
      color: #1e40af;
    }

    .btn-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #64748b;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.75rem;
      color: #334155;
    }

    .form-input {
      width: 100%;
      padding: 0.875rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 1rem;
      transition: all 0.2s;
    }

    .form-input:focus {
      outline: none;
      border-color: #3b82f6;
    }

    .players-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .player-card {
      padding: 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s;
      background: white;
      min-height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .player-card:hover {
      border-color: #3b82f6;
      background: #eff6ff;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(59, 130, 246, 0.1);
    }

    .player-card.selected {
      border-color: #10b981;
      background: #d1fae5;
      box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    }

    .player-name {
      font-size: 1rem;
      font-weight: 600;
      color: #0f172a;
      line-height: 1.4;
    }

    .divider {
      border-top: 1px solid #e2e8f0;
      margin: 1.5rem 0;
      position: relative;
    }

    .divider span {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background: white;
      padding: 0 1rem;
      color: #64748b;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .modal-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      margin-top: 2rem;
    }

    .btn-cancel, .btn-submit {
      padding: 0.875rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }

    .btn-cancel {
      background: #f1f5f9;
      color: #64748b;
    }

    .btn-submit {
      background: #10b981;
      color: white;
    }

    .btn-submit:hover {
      background: #059669;
    }

    .no-players-message {
      padding: 2rem;
      text-align: center;
      background: #fef3c7;
      border-radius: 8px;
      color: #92400e;
    }

    .info-box {
      background: #dbeafe;
      border-left: 4px solid #3b82f6;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      color: #1e40af;
    }

    .tempo-led-info {
      background: #dbeafe;
      border-left: 4px solid #3b82f6;
      padding: 0.75rem;
      border-radius: 8px;
      margin-bottom: 0.75rem;
      display: none;
      align-items: center;
      gap: 0.5rem;
    }

    .tempo-led-info.active {
      display: flex;
    }

    .tempo-led-info i {
      font-size: 1.25rem;
    }

    @media (max-width: 1023px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .match-display {
        grid-template-columns: 1fr;
        gap: 2rem;
      }

      .team-display.home {
        align-items: flex-start;
      }

      .team-display-name {
        font-size: 1.5rem;
      }

      .score-display {
        font-size: 3.5rem;
      }

      .score-actions {
        grid-template-columns: 1fr;
      }

      .players-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      }

      .header-top {
        flex-direction: column;
        align-items: stretch;
      }

      .header-actions {
        width: 100%;
      }

      .btn-display, .btn-finalizar-top {
        flex: 1;
        justify-content: center;
      }

      .match-info-row {
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
      }
    }
  </style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
<div class="main-content">
  <div class="content-wrapper">
    
    <a href="livescore.php" class="back-link">
      <i class='bx bx-arrow-back'></i>
      Voltar ao Live Score
    </a>

    <!-- Cabeçalho do Jogo -->
    <div class="match-header-card">
      <div class="header-top">
        <div class="live-indicator">
          <span class="live-dot"></span>
          AO VIVO
        </div>
        <div class="header-actions">
          <a href="display_livescore.php?id=<?= $match_id ?>" target="_blank" class="btn-display">
            <i class='bx bx-tv'></i>
            Ver Ecrã LED
          </a>
          <button class="btn-finalizar-top" onclick="finalizarJogo()">
            <i class='bx bx-stop-circle'></i>
            Finalizar Jogo
          </button>
        </div>
      </div>

      <div class="match-display">
        <div class="team-display home">
          <div class="team-display-name"><?= h($match['clube_nome']) ?></div>
          <div class="team-display-info"><?= h($match['escaloes']) ?> • Casa</div>
        </div>

        <div class="score-display">
          <?= intval($match['golos_marcados']) ?>
          <span class="score-separator">-</span>
          <?= intval($match['golos_sofridos']) ?>
        </div>

        <div class="team-display">
          <div class="team-display-name"><?= h($match['adversario']) ?></div>
          <div class="team-display-info">Visitante</div>
        </div>
      </div>

      <div class="match-info-row">
        <div class="match-info-item">
          <i class='bx bx-time-five'></i>
          <?= date('H:i', strtotime($match['data_jogo'])) ?>
        </div>
        <div class="match-info-item">
          <i class='bx bx-calendar'></i>
          <?= date('d/m/Y', strtotime($match['data_jogo'])) ?>
        </div>
        <div class="match-info-item">
          <i class='bx bx-map'></i>
          <?= h($match['local']) ?>
        </div>
      </div>

      <div class="score-actions">
        <button class="btn-golo-left" onclick="abrirModalGolo('casa')">
          <i class='bx bx-football'></i>
          Golo <?= h($match['clube_nome']) ?>
        </button>

        <button class="btn-golo-right" onclick="abrirModalGolo('fora')">
          <i class='bx bx-football'></i>
          Golo <?= h($match['adversario']) ?>
        </button>
      </div>
    </div>

    <!-- Timeline de Golos -->
    <div class="timeline-card">
      <h2>
        <i class='bx bx-list-ul'></i>
        Golos Marcados (<?= count($golos) ?>)
      </h2>

      <?php if (count($golos) > 0): ?>
      <div class="timeline">
        <?php foreach($golos as $golo): ?>
        <div class="timeline-item <?= $golo['equipa'] == 'fora' ? 'adversario' : '' ?>">
          <div class="timeline-minuto"><?= intval($golo['minuto']) ?>'</div>
          <div class="timeline-info">
            <div class="timeline-jogador"><?= h($golo['jogador_nome']) ?></div>
            <div class="timeline-equipa">
              <?= $golo['equipa'] == 'casa' ? h($match['clube_nome']) : h($match['adversario']) ?>
            </div>
          </div>
          <div class="timeline-actions">
            <button class="btn-delete-golo" onclick="removerGolo(<?= $golo['id'] ?>, '<?= h($golo['jogador_nome']) ?>')">
              <i class='bx bx-trash'></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-timeline">
        <i class='bx bx-football'></i>
        <p>Ainda não há golos registados neste jogo</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Modal Registar Golo -->
<div class="modal" id="modalGolo">
  <div class="modal-content">
    <div class="modal-header">
      <h3>
        <i class='bx bx-football'></i>
        Registar Golo
        <span class="team-badge" id="teamBadge"></span>
      </h3>
      <button class="btn-close" onclick="fecharModalGolo()">×</button>
    </div>

    <form id="formGolo" onsubmit="registarGolo(event)">
      <input type="hidden" id="matchId" value="<?= $match_id ?>">
      <input type="hidden" id="equipaSelecionada" name="equipa">

      <div class="form-group">
        <label>Minuto do Golo:</label>
        <div class="tempo-led-info" id="tempoLEDInfo">
          <i class='bx bx-time-five'></i>
          <div>
            <strong>Tempo atual no LED:</strong> <span id="tempoLEDDisplay">--</span> minutos
          </div>
        </div>
        <input type="number" id="minutoGolo" name="minuto" class="form-input" placeholder="Ex: 23" min="1" max="120" required>
        <small style="color: #64748b; display: block; margin-top: 0.5rem;">
          O minuto não pode ser superior ao tempo atual do jogo
        </small>
      </div>

      <!-- Seção de jogadores - apenas para equipa casa -->
      <div id="playersSection" style="display: none;">
        <div class="form-group">
          <label>Selecione o Jogador:</label>
          
          <?php if(count($jogadores) > 0): ?>
            <div class="players-grid">
              <?php foreach($jogadores as $jogador): ?>
              <div class="player-card" onclick="selecionarJogador(<?= $jogador['id'] ?>, '<?= h($jogador['nome']) ?>')">
                <div class="player-name"><?= h($jogador['nome']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="no-players-message">
              <i class='bx bx-error-circle'></i>
              <p>Nenhum jogador encontrado</p>
              <small>Adicione jogadores à equipa primeiro</small>
            </div>
          <?php endif; ?>
        </div>

        <div class="divider">
          <span>OU</span>
        </div>

        <div class="form-group">
          <label>Ou digite manualmente:</label>
          <input type="text" id="jogadorNome" name="jogador" class="form-input" placeholder="Digite o nome do jogador">
        </div>
      </div>

      <!-- Info para adversário -->
      <div id="adversarioInfo" style="display: none;">
        <div class="info-box">
          <i class='bx bx-info-circle'></i>
          <strong>Golo do Adversário</strong><br>
          Apenas o minuto será registado.
        </div>
        <input type="hidden" id="jogadorNomeAdversario" name="jogador" value="Adversário">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="fecharModalGolo()">Cancelar</button>
        <button type="submit" class="btn-submit">
          Registar Golo
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../../toast.js"></script>
<script>
let equipaSelecionada = null;
let jogadorSelecionadoId = null;
let tempoAtualLED = 0;

// Função para obter tempo atual do LED
function obterTempoAtualLED() {
  fetch('get_tempo_atual.php?match_id=<?= $match_id ?>')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        tempoAtualLED = data.tempo_atual;
        // Atualizar o max do input
        const minutoInput = document.getElementById('minutoGolo');
        if (minutoInput && tempoAtualLED > 0) {
          minutoInput.max = tempoAtualLED;
          
          // Mostrar info do tempo LED
          const infoDiv = document.getElementById('tempoLEDInfo');
          const displaySpan = document.getElementById('tempoLEDDisplay');
          if (infoDiv && displaySpan) {
            displaySpan.textContent = tempoAtualLED;
            infoDiv.classList.add('active');
          }
        }
      }
    })
    .catch(err => console.error('Erro ao obter tempo LED:', err));
}

// Atualizar tempo LED a cada 5 segundos
setInterval(obterTempoAtualLED, 5000);
obterTempoAtualLED(); // Chamada inicial

function abrirModalGolo(tipo) {
  equipaSelecionada = tipo;
  document.getElementById('equipaSelecionada').value = tipo;
  document.getElementById('modalGolo').classList.add('active');
  
  // Obter tempo atual antes de abrir
  obterTempoAtualLED();
  
  document.getElementById('minutoGolo').value = '';
  jogadorSelecionadoId = null;
  
  // Limpar seleções anteriores
  document.querySelectorAll('.player-card').forEach(card => {
    card.classList.remove('selected');
  });
  
  const teamBadge = document.getElementById('teamBadge');
  const playersSection = document.getElementById('playersSection');
  const adversarioInfo = document.getElementById('adversarioInfo');
  const jogadorNomeInput = document.getElementById('jogadorNome');
  
  if (tipo === 'casa') {
    // Golo da equipa casa - mostrar jogadores
    teamBadge.textContent = '<?= h($match['clube_nome']) ?>';
    teamBadge.className = 'team-badge badge-casa';
    playersSection.style.display = 'block';
    adversarioInfo.style.display = 'none';
    if (jogadorNomeInput) {
      jogadorNomeInput.value = '';
      jogadorNomeInput.required = true;
    }
  } else {
    // Golo da equipa adversária - só pedir minuto
    teamBadge.textContent = '<?= h($match['adversario']) ?>';
    teamBadge.className = 'team-badge badge-fora';
    playersSection.style.display = 'none';
    adversarioInfo.style.display = 'block';
    if (jogadorNomeInput) {
      jogadorNomeInput.required = false;
    }
  }
}

function fecharModalGolo() {
  document.getElementById('modalGolo').classList.remove('active');
}

function selecionarJogador(id, nome) {
  jogadorSelecionadoId = id;
  document.getElementById('jogadorNome').value = nome;
  
  document.querySelectorAll('.player-card').forEach(card => {
    card.classList.remove('selected');
  });
  event.currentTarget.classList.add('selected');
}

function registarGolo(e) {
  e.preventDefault();
  
  const matchId = document.getElementById('matchId').value;
  const equipa = document.getElementById('equipaSelecionada').value;
  const minuto = parseInt(document.getElementById('minutoGolo').value);
  
  // Validar se o minuto não excede o tempo atual do LED
  if (tempoAtualLED > 0 && minuto > tempoAtualLED) {
    toast.warning('Minuto Inválido', `O minuto não pode ser superior ao tempo atual do jogo (${tempoAtualLED})`);
    return;
  }
  
  let jogador;
  if (equipa === 'casa') {
    // Para equipa casa, pegar o nome do jogador
    jogador = document.getElementById('jogadorNome').value;
    if (!jogador || jogador.trim() === '') {
      toast.warning('Atenção', 'Por favor, selecione ou digite o nome do jogador');
      return;
    }
  } else {
    // Para adversário, usar sempre "Adversário"
    jogador = 'Adversário';
  }
  
  if (!equipa) {
    toast.warning('Atenção', 'Equipa não selecionada');
    return;
  }
  
  toast.info('A registar golo...', 'Aguarde');
  
  fetch('registar_golo.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `match_id=${matchId}&equipa=${equipa}&jogador=${encodeURIComponent(jogador)}&minuto=${minuto}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const mensagem = equipa === 'casa' ? `${jogador} - ${minuto}'` : `Minuto ${minuto}'`;
      toast.success('Golo Registado!', mensagem);
      fecharModalGolo();
      setTimeout(() => window.location.reload(), 1500);
    } else {
      toast.error('Erro', data.message || 'Não foi possível registar o golo');
    }
  })
  .catch(err => {
    console.error('Erro:', err);
    toast.error('Erro', 'Problema ao contactar o servidor');
  });
}

function removerGolo(goloId, jogadorNome) {
  if (!confirm(`Tem certeza que deseja remover o golo de ${jogadorNome}?`)) {
    return;
  }

  // Mostrar indicador de loading
  const btn = event.currentTarget;
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';

  fetch('remover_golo.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `golo_id=${goloId}`
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Erro na resposta do servidor');
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      toast.success('Golo Removido', 'O golo foi removido com sucesso');
      // Reload após 1 segundo
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      // Restaurar botão em caso de erro
      btn.disabled = false;
      btn.innerHTML = originalHTML;
      toast.error('Erro', data.message || 'Não foi possível remover o golo');
    }
  })
  .catch(err => {
    console.error('Erro ao remover golo:', err);
    // Restaurar botão em caso de erro
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    toast.error('Erro', 'Problema ao contactar o servidor. Tente novamente.');
  });
}

function finalizarJogo() {
  if (!confirm('Tem certeza que deseja finalizar este jogo? O estado será alterado para "Concluído".')) {
    return;
  }

  toast.info('A finalizar...', 'Aguarde');
  
  fetch('finalizar_jogo.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `match_id=<?= $match_id ?>`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      toast.success('Jogo Finalizado!', 'O jogo foi concluído com sucesso');
      setTimeout(() => window.location.href = 'livescore.php', 2000);
    } else {
      toast.error('Erro', data.message || 'Não foi possível finalizar');
    }
  })
  .catch(err => {
    console.error('Erro:', err);
    toast.error('Erro', 'Problema ao contactar o servidor');
  });
}

// Auto-refresh a cada 30 segundos
setInterval(() => location.reload(), 30000);
</script>
</body>
</html></parameter>