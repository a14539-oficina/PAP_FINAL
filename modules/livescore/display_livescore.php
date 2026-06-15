<?php
session_start();
require('../../config/db.php');

// Buscar jogo específico se ID foi passado, senão busca jogo ativo
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($match_id > 0) {
  $stmt = $conn->prepare("
    SELECT m.id, m.data_jogo, m.adversario, 
           CONCAT(c.nome) AS equipa, 
           t.escaloes, m.local,
           m.golos_marcados, m.golos_sofridos,
           m.estado,
           c.nome as clube_nome,
           c.logo as clube_logo,
           t.nome as team_nome,
           t.logo as team_logo,
           m.tempo_jogo,
           m.competicao
    FROM matches m
    JOIN teams t ON t.id = m.team_id
    JOIN clubs c ON c.id = t.club_id
    WHERE m.id = ?
  ");
  $stmt->bind_param("i", $match_id);
  $stmt->execute();
  $jogo = $stmt->get_result()->fetch_assoc();
} else {
  $sql = "
    SELECT m.id, m.data_jogo, m.adversario, 
           CONCAT(c.nome) AS equipa, 
           t.escaloes, m.local,
           m.golos_marcados, m.golos_sofridos,
           m.estado,
           c.nome as clube_nome,
           c.logo as clube_logo,
           t.nome as team_nome,
           t.logo as team_logo,
           m.tempo_jogo,
           m.competicao
    FROM matches m
    JOIN teams t ON t.id = m.team_id
    JOIN clubs c ON c.id = t.club_id
    WHERE LOWER(m.estado) = 'a decorrer'
    ORDER BY m.data_jogo DESC
    LIMIT 1
  ";
  
  $result = $conn->query($sql);
  $jogo = $result ? $result->fetch_assoc() : null;
}

// Buscar golos se houver jogo
$golos = [];
if ($jogo) {
  $columnCheck = $conn->query("SHOW COLUMNS FROM match_goals LIKE '%jogador%'");
  $jogadorColumn = 'jogador';
  if ($columnCheck && $columnCheck->num_rows > 0) {
    $col = $columnCheck->fetch_assoc();
    $jogadorColumn = $col['Field'];
  }

  $golosStmt = $conn->prepare("
    SELECT id, match_id, equipa, $jogadorColumn as jogador_nome, minuto, created_at 
    FROM match_goals 
    WHERE match_id = ? 
    ORDER BY minuto ASC, created_at ASC
  ");
  $golosStmt->bind_param("i", $jogo['id']);
  $golosStmt->execute();
  $golos = $golosStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Score - Flashscore Style</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f8fafc;
      color: #1e293b;
      min-height: 100vh;
      padding: 20px;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
    }

    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #64748b;
      margin-bottom: 20px;
      text-transform: uppercase;
      font-weight: 600;
    }

    .breadcrumb span {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .match-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid #e2e8f0;
    }

    .match-header {
      background: #f8fafc;
      padding: 16px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e2e8f0;
    }

    .match-date {
      font-size: 13px;
      color: #64748b;
      font-weight: 600;
    }

    .favorite-btn {
      background: none;
      border: none;
      color: #8b97a6;
      font-size: 20px;
      cursor: pointer;
      transition: color 0.3s;
    }

    .favorite-btn:hover {
      color: #ffd700;
    }

    .match-main {
      padding: 32px 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 20px;
    }

    .timer-display-small {
      font-size: 22px;
      font-weight: 700;
      font-family: 'Courier New', monospace;
      color: #10b981;
      letter-spacing: 2px;
      order: -1;
      text-align: center;
      transition: all 0.3s;
    }

    .timer-display-small.intervalo {
      color: #f59e0b;
      animation: pulseIntervalo 1.5s ease-in-out infinite;
    }

    /* Intervalo Screen */
    .intervalo-screen {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.95);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      animation: fadeInIntervalo 0.5s ease;
    }

    .intervalo-screen.show {
      display: flex;
    }

    @keyframes fadeInIntervalo {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .intervalo-content {
      text-align: center;
      animation: slideInIntervalo 0.6s ease;
    }

    @keyframes slideInIntervalo {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .intervalo-icon {
      font-size: 100px;
      color: #f59e0b;
      margin-bottom: 30px;
      animation: pulseIcon 2s ease-in-out infinite;
    }

    @keyframes pulseIcon {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    .intervalo-title {
      font-size: 72px;
      font-weight: 900;
      color: #fff;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .intervalo-subtitle {
      font-size: 32px;
      color: #94a3b8;
      font-weight: 600;
      margin-bottom: 40px;
    }

    .intervalo-countdown {
      font-size: 48px;
      font-weight: 700;
      color: #f59e0b;
      font-family: 'Courier New', monospace;
    }

    .btn-start-second-half {
      margin-top: 30px;
      padding: 16px 40px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 18px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-start-second-half:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
    }

    .btn-start-second-half:active {
      transform: translateY(-1px);
    }

    .btn-start-second-half i {
      font-size: 22px;
    }

    .teams-scores {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      gap: 40px;
      max-width: 900px;
      margin: 0 auto;
    }

    .team {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      flex: 1;
    }

    .team.away {
      flex-direction: row-reverse;
    }

    .team-name {
      font-size: 32px;
      font-weight: 700;
      color: #0f172a;
      text-align: center;
      flex: 1;
      max-width: 250px;
    }

    .score {
      font-size: 90px;
      font-weight: 700;
      font-family: 'Courier New', monospace;
      color: #0f172a;
      line-height: 1;
      min-width: 120px;
      text-align: center;
    }

    .score-separator {
      font-size: 60px;
      color: #cbd5e1;
      font-weight: 700;
      margin: 0;
      text-align: center;
      flex-shrink: 0;
    }

    .btn-start-small {
      padding: 8px 20px;
      background: #10b981;
      color: white;
      border: none;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all 0.3s;
      order: 1;
      margin: 0 auto;
    }

    .btn-start-small:hover {
      background: #059669;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-start-small i {
      font-size: 14px;
    }

    /* Goal Animation */
    .goal-animation {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.95);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      animation: fadeInGoal 0.3s ease;
    }

    .goal-animation.show {
      display: flex;
    }

    @keyframes fadeInGoal {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .goal-animation-content {
      text-align: center;
      animation: goalPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    @keyframes goalPop {
      0% { transform: scale(0) rotate(-180deg); opacity: 0; }
      50% { transform: scale(1.2) rotate(10deg); }
      100% { transform: scale(1) rotate(0deg); opacity: 1; }
    }

    .goal-animation-icon {
      font-size: 120px;
      color: #ff9800;
      margin-bottom: 20px;
      animation: goalBounce 0.8s ease infinite;
    }

    @keyframes goalBounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-20px); }
    }

    .goal-animation-text {
      font-size: 80px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      margin-bottom: 20px;
      background: linear-gradient(135deg, #ff9800, #f57c00);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: 0 0 60px rgba(255, 152, 0, 0.5);
      animation: goalGlow 1.5s ease-in-out infinite;
    }

    @keyframes goalGlow {
      0%, 100% { filter: drop-shadow(0 0 20px rgba(255, 152, 0, 0.6)); }
      50% { filter: drop-shadow(0 0 40px rgba(255, 152, 0, 0.9)); }
    }

    .goal-animation-team {
      font-size: 48px;
      font-weight: 800;
      color: #fff;
      margin-top: 10px;
      letter-spacing: 0.05em;
    }

    .goal-animation-player {
      font-size: 36px;
      color: #8b97a6;
      margin-top: 20px;
      font-weight: 600;
    }

    .goal-confetti {
      position: absolute;
      width: 10px;
      height: 10px;
      background: #ff9800;
      animation: confettiFall 3s linear;
    }

    @keyframes confettiFall {
      0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
      100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
    }

    .tabs {
      display: flex;
      border-bottom: 1px solid #e2e8f0;
      padding: 0 24px;
      background: #f8fafc;
    }

    .tab {
      padding: 16px 24px;
      background: none;
      border: none;
      color: #64748b;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      transition: color 0.3s;
    }

    .tab.active {
      color: #0f172a;
    }

    .tab.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: #10b981;
      border-radius: 3px 3px 0 0;
    }

    .goals-section {
      padding: 24px;
    }

    .goals-header {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 20px;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .goal-item {
      display: flex;
      align-items: center;
      padding: 12px 16px;
      margin-bottom: 8px;
      background: #f8fafc;
      border-radius: 8px;
      border-left: 3px solid #10b981;
      transition: background 0.3s;
    }

    .goal-item:hover {
      background: #f1f5f9;
    }

    .goal-item.away {
      border-left-color: #3b82f6;
    }

    .goal-minute {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 80px;
      font-size: 14px;
      font-weight: 700;
      color: #0f172a;
    }

    .goal-icon {
      font-size: 18px;
      color: #f59e0b;
    }

    .goal-details {
      flex: 1;
    }

    .goal-player {
      font-size: 15px;
      font-weight: 600;
      color: #0f172a;
      margin-bottom: 2px;
    }

    .goal-team {
      font-size: 12px;
      color: #64748b;
    }

    .no-goals {
      text-align: center;
      padding: 40px;
      color: #94a3b8;
      font-size: 14px;
    }

    .no-match {
      text-align: center;
      padding: 80px 20px;
      color: #64748b;
    }

    .no-match i {
      font-size: 80px;
      margin-bottom: 20px;
      color: #cbd5e1;
    }

    .no-match h2 {
      font-size: 28px;
      margin-bottom: 10px;
      color: #475569;
    }

    .no-match p {
      font-size: 16px;
      color: #64748b;
    }

    .goal-item.home {
      border-left-color: #10b981;
    }

    @media (max-width: 768px) {
      .teams-scores {
        flex-direction: column;
        gap: 25px;
      }

      .team {
        width: 100%;
        justify-content: center;
      }

      .team.away {
        flex-direction: row;
      }

      .team-name {
        font-size: 24px;
      }

      .score {
        font-size: 64px;
      }

      .score-separator {
        font-size: 40px;
      }

      .timer-display-small {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if (!$jogo): ?>
      <div class="no-match">
        <i class='bx bx-football'></i>
        <h2>Nenhum jogo ativo</h2>
        <p>Configure um jogo no painel de controlo</p>
      </div>
    <?php else: ?>

    <div class="breadcrumb">
      <span><i class='bx bx-football'></i> FUTEBOL</span>
      <span>›</span>
      <span><?= h($jogo['competicao'] ?: 'COMPETIÇÃO') ?></span>
    </div>

    <div class="match-card">
      <div class="match-header">
        <div class="match-date" id="matchDate"><?= date('d.m.Y H:i', strtotime($jogo['data_jogo'])) ?></div>
        <button class="favorite-btn">
          <i class='bx bx-star'></i>
        </button>
      </div>

      <div class="match-main">
        <div class="timer-display-small" id="timerDisplaySmall">00:00</div>

        <div class="teams-scores">
          <div class="team">
            <div class="team-name"><?= h($jogo['equipa']) ?></div>
            <div class="score" id="scoreHome">0</div>
          </div>

          <div class="score-separator">-</div>

          <div class="team away">
            <div class="team-name"><?= h($jogo['adversario']) ?></div>
            <div class="score" id="scoreAway">0</div>
          </div>
        </div>

        <?php if (strtolower($jogo['estado']) == 'agendado'): ?>
          <button class="btn-start-small" id="btnStartSmall" onclick="startMatchTimer()">
            <i class='bx bx-play'></i> Começar
          </button>
        <?php endif; ?>
      </div>

      <div class="tabs">
        <button class="tab active">SUMÁRIO</button>
      </div>

      <div class="goals-section">
        <div class="goals-header">
          <i class='bx bxs-football'></i>
          Golos do Jogo (<span id="goalCount">0</span>)
        </div>

        <div id="goalsList"></div>
      </div>
    </div>

    <?php endif; ?>
  </div>

  <!-- Goal Animation -->
  <div class="goal-animation" id="goalAnimation">
    <div class="goal-animation-content">
      <div class="goal-animation-icon">
        <i class='bx bxs-football'></i>
      </div>
      <div class="goal-animation-text">GOLOOO!</div>
      <div class="goal-animation-team" id="goalTeamName"></div>
      <div class="goal-animation-player" id="goalPlayerName"></div>
    </div>
  </div>

  <!-- Intervalo Screen -->
  <div class="intervalo-screen" id="intervaloScreen">
    <div class="intervalo-content">
      <div class="intervalo-icon">
        <i class='bx bx-time-five'></i>
      </div>
      <div class="intervalo-title">INTERVALO</div>
      <div class="intervalo-subtitle">Primeira parte concluída</div>
      <button class="btn-start-second-half" id="btnStartSecondHalf" onclick="startSecondHalf()">
        <i class='bx bx-play-circle'></i>
        Começar Segunda Parte
      </button>
    </div>
  </div>

  <script>
    // Dados dos golos vindos do PHP
    const todosGolos = <?= json_encode($golos) ?>;
    const equipaCasa = '<?= h($jogo['equipa']) ?>';
    const equipaFora = '<?= h($jogo['adversario']) ?>';
    
    let timer = null;
    let seconds = <?= intval($jogo['tempo_jogo'] ?? 0) ?>;
    let period = 1;
    let matchStarted = <?= strtolower($jogo['estado'] ?? 'agendado') != 'agendado' ? 'true' : 'false' ?>;
    let isIntervalo = false;
    
    // Array para controlar quais golos já foram mostrados
    let golosMostrados = [];
    let currentScoreHome = 0;
    let currentScoreAway = 0;

    function startMatchTimer() {
      if (timer) return;
      
      const btnStart = document.getElementById('btnStartSmall');
      if (btnStart) btnStart.style.display = 'none';
      
      matchStarted = true;
      startTimer();
    }

    function startTimer() {
      if (timer) return;
      
      timer = setInterval(() => {
        seconds++;
        const currentMinute = Math.floor(seconds / 60);
        updateTimerDisplay();
        
        // Verificar se há golos para mostrar neste minuto
        checkForGoals(currentMinute);
        
        // Fim do 1º tempo aos 45 minutos (2700 segundos)
        if (seconds === 2700 && period === 1 && !isIntervalo) {
          pauseTimer();
          isIntervalo = true;
          showIntervaloScreen();
        }
        
        // Fim do jogo aos 90 minutos (5400 segundos)
        if (seconds === 5400 && period === 2) {
          pauseTimer();
          showFimJogoScreen();
        }
      }, 1000);
    }

    function showIntervaloScreen() {
      const timerEl = document.getElementById('timerDisplaySmall');
      timerEl.textContent = '45:00';
      timerEl.classList.add('intervalo');
      
      // Mostrar tela de intervalo
      const intervaloScreen = document.getElementById('intervaloScreen');
      intervaloScreen.classList.add('show');
    }

    function startSecondHalf() {
      const intervaloScreen = document.getElementById('intervaloScreen');
      const timerEl = document.getElementById('timerDisplaySmall');
      
      // Esconder tela de intervalo
      intervaloScreen.classList.remove('show');
      timerEl.classList.remove('intervalo');
      
      // Iniciar 2º tempo
      isIntervalo = false;
      period = 2;
      startTimer();
    }

    function showFimJogoScreen() {
      const timerEl = document.getElementById('timerDisplaySmall');
      timerEl.textContent = 'FIM DE JOGO';
      timerEl.style.color = '#ef4444';
    }

    function pauseTimer() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function updateTimerDisplay() {
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      
      // Mostrar minuto do jogo (não segundos totais)
      let displayMinute = mins;
      if (period === 2 && mins >= 45) {
        displayMinute = mins; // Mantém contagem contínua
      }
      
      const display = String(displayMinute).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
      
      const timerSmall = document.getElementById('timerDisplaySmall');
      if (timerSmall && !isIntervalo) {
        timerSmall.textContent = display;
      }
    }

    function checkForGoals(currentMinute) {
      // Percorrer todos os golos
      todosGolos.forEach(golo => {
        const goloMinute = parseInt(golo.minuto);
        const goloId = golo.id;
        
        // Se o minuto atual chegou ao minuto do golo E ainda não foi mostrado
        if (currentMinute >= goloMinute && !golosMostrados.includes(goloId)) {
          golosMostrados.push(goloId);
          
          // Atualizar o placar
          if (golo.equipa === 'casa') {
            currentScoreHome++;
          } else {
            currentScoreAway++;
          }
          
          // Atualizar placar no ecrã
          document.getElementById('scoreHome').textContent = currentScoreHome;
          document.getElementById('scoreAway').textContent = currentScoreAway;
          
          // Mostrar animação
          const teamName = golo.equipa === 'casa' ? equipaCasa : equipaFora;
          showGoalAnimation(teamName, golo.jogador_nome);
          
          // Atualizar lista de golos
          updateGoalsList();
        }
      });
    }

    function updateGoalsList() {
      const goalsList = document.getElementById('goalsList');
      const goalCount = document.getElementById('goalCount');
      
      if (!goalsList) return;
      
      // Filtrar apenas os golos já mostrados
      const golosVisiveis = todosGolos.filter(g => golosMostrados.includes(g.id));
      
      goalCount.textContent = golosVisiveis.length;
      
      if (golosVisiveis.length === 0) {
        goalsList.innerHTML = '<div class="no-goals">Nenhum golo registado</div>';
        return;
      }
      
      let html = '';
      let scoreCasa = 0;
      let scoreFora = 0;
      
      golosVisiveis.forEach(golo => {
        if (golo.equipa === 'casa') scoreCasa++;
        else scoreFora++;
        
        html += `
          <div class="goal-item ${golo.equipa === 'casa' ? 'home' : 'away'}">
            <div class="goal-minute">
              <i class='bx bxs-football goal-icon'></i>
              ${parseInt(golo.minuto)}'
            </div>
            <div class="goal-details">
              <div class="goal-player">${golo.jogador_nome}</div>
              <div class="goal-team">${scoreCasa} - ${scoreFora}</div>
            </div>
          </div>
        `;
      });
      
      goalsList.innerHTML = html;
    }

    function showGoalAnimation(teamName, playerName) {
      const animation = document.getElementById('goalAnimation');
      const teamEl = document.getElementById('goalTeamName');
      const playerEl = document.getElementById('goalPlayerName');
      
      teamEl.textContent = teamName;
      playerEl.textContent = playerName;
      
      animation.classList.add('show');
      
      createConfetti();
      
      setTimeout(() => {
        animation.classList.remove('show');
      }, 4000);
    }

    function createConfetti() {
      const colors = ['#ff9800', '#f57c00', '#ffd54f', '#ffb300', '#ff6f00'];
      const animation = document.getElementById('goalAnimation');
      
      for (let i = 0; i < 50; i++) {
        setTimeout(() => {
          const confetti = document.createElement('div');
          confetti.className = 'goal-confetti';
          confetti.style.left = Math.random() * 100 + '%';
          confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
          confetti.style.animationDelay = Math.random() * 0.5 + 's';
          animation.appendChild(confetti);
          
          setTimeout(() => confetti.remove(), 3000);
        }, i * 30);
      }
    }

    // Initialize display
    updateTimerDisplay();
    updateGoalsList();
    
    // Auto start if match is running
    if (matchStarted) {
      startTimer();
    }

    // Auto-refresh para sincronizar com o servidor
    setInterval(() => {
      // Adicionar timestamp para evitar cache
      const url = window.location.pathname + window.location.search + 
                  (window.location.search.includes('?') ? '&' : '?') + 
                  '_t=' + Date.now();
      
      fetch(url)
        .then(r => r.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          
          // Extrair os novos golos do script
          const scripts = doc.getElementsByTagName('script');
          for (let script of scripts) {
            const content = script.textContent;
            if (content.includes('const todosGolos')) {
              const match = content.match(/const todosGolos = (\[.*?\]);/s);
              if (match) {
                const newGolos = JSON.parse(match[1]);
                
                // IMPORTANTE: Substituir array completo para remover golos deletados
                todosGolos.length = 0; // Limpa o array
                newGolos.forEach(golo => {
                  todosGolos.push(golo);
                });
                
                // Recalcular golos mostrados baseado no tempo atual
                const currentMinute = Math.floor(seconds / 60);
                golosMostrados = [];
                currentScoreHome = 0;
                currentScoreAway = 0;
                
                // Mostrar todos os golos até o minuto atual (sem animação)
                todosGolos.forEach(golo => {
                  const goloMinute = parseInt(golo.minuto);
                  if (currentMinute >= goloMinute) {
                    golosMostrados.push(golo.id);
                    if (golo.equipa === 'casa') {
                      currentScoreHome++;
                    } else {
                      currentScoreAway++;
                    }
                  }
                });
                
                // Atualizar placar e lista
                document.getElementById('scoreHome').textContent = currentScoreHome;
                document.getElementById('scoreAway').textContent = currentScoreAway;
                updateGoalsList();
              }
              break;
            }
          }
        })
        .catch(err => console.log('Refresh error:', err));
    }, 3000);
  </script>
</body>
</html>