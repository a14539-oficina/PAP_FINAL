<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('../../config/db.php');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);
$user_role = $_SESSION['user_role'] ?? '';
$isAdmin = ($user_role == 1);

// Buscar temporadas disponíveis
$temporadasQuery = $conn->query("
  SELECT id, nome FROM seasons ORDER BY data_inicio DESC
");
$temporadas = [];
if ($temporadasQuery) {
  while ($row = $temporadasQuery->fetch_assoc()) {
    $temporadas[] = ['id' => $row['id'], 'nome' => $row['nome']];
  }
}
if (count($temporadas) == 0) {
  $temporadas[] = ['id' => 1, 'nome' => '24/25'];
}

$temporadaSelecionadaId = isset($_GET['temporada']) ? (int)$_GET['temporada'] : $temporadas[0]['id'];

// Buscar jogos agendados ou a decorrer
if ($isAdminPrincipal) {
  $sql = "
    SELECT m.id, m.data_jogo, m.adversario, 
           CONCAT(c.nome) AS equipa, 
           t.escaloes, m.local,
           m.golos_marcados, m.golos_sofridos,
           m.estado,
           c.nome as clube_nome,
           t.nome as team_nome
    FROM matches m
    JOIN teams t ON t.id = m.team_id
    JOIN clubs c ON c.id = t.club_id
    WHERE m.season_id = ? 
      AND (LOWER(m.estado) = 'agendado' OR LOWER(m.estado) = 'a decorrer')
    ORDER BY m.data_jogo DESC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $temporadaSelecionadaId);
} else {
  $sql = "
    SELECT m.id, m.data_jogo, m.adversario, 
           CONCAT(c.nome) AS equipa, 
           t.escaloes, m.local,
           m.golos_marcados, m.golos_sofridos,
           m.estado,
           c.nome as clube_nome,
           t.nome as team_nome
    FROM matches m
    JOIN teams t ON t.id = m.team_id
    JOIN clubs c ON c.id = t.club_id
    WHERE m.season_id = ? 
      AND t.club_id = ? 
      AND (LOWER(m.estado) = 'agendado' OR LOWER(m.estado) = 'a decorrer')
    ORDER BY m.data_jogo DESC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $temporadaSelecionadaId, $user_club_id);
}

$stmt->execute();
$res = $stmt->get_result();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Score - SportGes</title>
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
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      background: white;
      padding: 1.5rem 2rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .page-header h1 {
      font-size: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .live-badge {
      background: #ef4444;
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }

    .season-select {
      padding: 0.5rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.875rem;
      cursor: pointer;
    }

    .matches-grid {
      display: grid;
      gap: 1rem;
    }

    .match-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      border-left: 4px solid #ef4444;
      cursor: pointer;
      transition: all 0.2s;
    }

    .match-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transform: translateY(-2px);
    }

    .match-card.agendado {
      border-left-color: #3b82f6;
    }

    .match-status {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.75rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .match-status.live {
      background: #fee2e2;
      color: #dc2626;
    }

    .match-status.scheduled {
      background: #dbeafe;
      color: #2563eb;
    }

    .match-content {
      display: grid;
      grid-template-columns: 1fr auto 1fr auto;
      align-items: center;
      gap: 1.5rem;
    }

    .team-home, .team-away {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .team-home {
      text-align: right;
    }

    .team-name {
      font-size: 1.125rem;
      font-weight: 700;
      color: #0f172a;
    }

    .team-info {
      font-size: 0.8125rem;
      color: #64748b;
    }

    .score {
      font-size: 2rem;
      font-weight: 900;
      color: #ef4444;
      padding: 0 1rem;
    }

    .match-actions {
      display: flex;
      gap: 0.5rem;
    }

    .btn-golo {
      background: #10b981;
      color: white;
      border: none;
      padding: 0.75rem 1.25rem;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
    }

    .btn-golo:hover {
      background: #059669;
      transform: translateY(-1px);
    }

    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: white;
      border-radius: 12px;
    }

    .empty-state i {
      font-size: 4rem;
      color: #cbd5e1;
      margin-bottom: 1rem;
    }

    @media (max-width: 1023px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .match-content {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .team-home {
        text-align: left;
      }

      .score {
        text-align: center;
      }

      .match-actions {
        justify-content: center;
      }
    }
  </style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
<div class="main-content">
  <div class="content-wrapper">
    
    <div class="page-header">
      <h1>
        <i class='bx bx-broadcast'></i>
        Live Score
        <span class="live-badge">AO VIVO</span>
      </h1>
      <select class="season-select" onchange="window.location.href='?temporada='+this.value">
        <?php foreach($temporadas as $temp): ?>
          <option value="<?= $temp['id'] ?>" <?= $temporadaSelecionadaId == $temp['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($temp['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if($res && $res->num_rows > 0): ?>
    <div class="matches-grid">
      <?php while($r = $res->fetch_assoc()): 
            $golosMarcados = intval($r['golos_marcados']);
            $golosSofridos = intval($r['golos_sofridos']);
            $estado = strtolower($r['estado']);
            $isLive = ($estado == 'a decorrer');
            $cardClass = $isLive ? '' : 'agendado';
          ?>
          <div class="match-card <?= $cardClass ?>" onclick="abrirJogo(<?= $r['id'] ?>)">
            <?php if($isLive): ?>
              <span class="match-status live">
                <i class='bx bx-broadcast'></i>
                AO VIVO
              </span>
            <?php else: ?>
              <span class="match-status scheduled">
                <i class='bx bx-calendar'></i>
                AGENDADO
              </span>
            <?php endif; ?>

            <div class="match-content">
              <div class="team-home">
                <div class="team-name"><?= h($r['equipa']) ?></div>
                <div class="team-info"><?= h($r['escaloes']) ?> • <?= h($r['local']) ?></div>
              </div>

              <div class="score"><?= $golosMarcados ?> - <?= $golosSofridos ?></div>

              <div class="team-away">
                <div class="team-name"><?= h($r['adversario']) ?></div>
                <div class="team-info">Visitante</div>
              </div>

              <?php if($isAdmin): ?>
              <div class="match-actions" onclick="event.stopPropagation()">
                
              </div>
              <?php endif; ?>
            </div>
          </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <i class='bx bx-football'></i>
      <h3>Nenhum jogo disponível</h3>
      <p>Não há jogos agendados ou em curso neste momento</p>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="../../toast.js"></script>
<script>
// Abrir página de edição do jogo
function abrirJogo(id) {
  window.location.href = 'editar_livescore.php?id=' + id;
}

// Auto-refresh a cada 30 segundos
setInterval(() => location.reload(), 30000);
</script>
</body>
</html>