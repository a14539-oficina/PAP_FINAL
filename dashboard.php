<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('config/db.php');

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

// Verificar se a tabela tem coluna club_id
$checkColumn = $conn->query("SHOW COLUMNS FROM receitas LIKE 'club_id'");
$hasClubColumn = ($checkColumn && $checkColumn->num_rows > 0);

$totalEquipas = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM teams");
if ($res && $row = $res->fetch_assoc()) {
  $totalEquipas = $row['total'];
}

// Verificar papéis dos usuários
$userRole = isset($_SESSION['user_role']) ? strtolower($_SESSION['user_role']) : '';

$isAdmin = in_array($userRole, ['admin', '1', '0']);
$isDiretor = in_array($userRole, ['diretor', '2']);
$isTreinador = in_array($userRole, ['treinador', '3']);
$isJogador = in_array($userRole, ['jogador', '4']);

// Permissões baseadas em função
$canViewFinancial = $isAdmin;
$canViewAllModules = $isAdmin || $isDiretor;
$canViewTeamModules = $isAdmin || $isDiretor || $isTreinador;
$canViewBasic = true;

// ======================================
// BUSCAR TOTAIS DE RECEITAS E DESPESAS
// ======================================
$totalReceitas = 0;
$totalDespesas = 0;
$saldo = 0;

if ($canViewFinancial) {
  try {
    // TOTAL RECEITAS (receitas normais + mensalidades pagas)
    if ($hasClubColumn) {
      if ($isAdminPrincipal) {
        // Admin Principal: soma TODAS as receitas
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas");
        $stmt->execute();
        $totalReceitasNormais = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        // Mensalidades pagas de TODOS os clubes
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM mensalidades WHERE status = 'pago'");
        $stmt->execute();
        $totalMensalidadesPagas = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
      } else {
        // Admin de clube específico
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE club_id = ?");
        $stmt->bind_param("i", $user_club_id);
        $stmt->execute();
        $totalReceitasNormais = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        // Mensalidades pagas do clube
        $stmt = $conn->prepare("
          SELECT COALESCE(SUM(m.valor), 0) as total 
          FROM mensalidades m 
          INNER JOIN players p ON m.jogador_id = p.id 
          INNER JOIN teams t ON p.team_id = t.id 
          WHERE m.status = 'pago' AND t.club_id = ?
        ");
        $stmt->bind_param("i", $user_club_id);
        $stmt->execute();
        $totalMensalidadesPagas = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
      }
    } else {
      // Sem coluna club_id
      $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas");
      $stmt->execute();
      $totalReceitasNormais = $stmt->get_result()->fetch_assoc()['total'];
      $stmt->close();
      
      $totalMensalidadesPagas = 0;
    }
    
    $totalReceitas = $totalReceitasNormais + $totalMensalidadesPagas;
    
    // TOTAL DESPESAS
    if ($hasClubColumn) {
      if ($isAdminPrincipal) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas");
        $stmt->execute();
      } else {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas WHERE club_id = ?");
        $stmt->bind_param("i", $user_club_id);
        $stmt->execute();
      }
    } else {
      $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas");
      $stmt->execute();
    }
    
    $totalDespesas = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $saldo = $totalReceitas - $totalDespesas;
    
  } catch (Exception $e) {
    $totalReceitas = 0;
    $totalDespesas = 0;
    $saldo = 0;
  }
}

// ======================================
// BUSCAR ÚLTIMOS 6 MESES PARA O GRÁFICO
// ======================================
$receitasMensais = [];
$despesasMensais = [];
$lucroMensal = [];
$meses = [];

if ($canViewFinancial) {
  try {
    for ($i = 5; $i >= 0; $i--) {
      $mes = date('Y-m', strtotime("-$i months"));
      $mesLabel = date('M/y', strtotime("-$i months"));
      $meses[] = $mesLabel;
      
      // RECEITAS DO MÊS (receitas normais + mensalidades pagas)
      if ($hasClubColumn) {
        if ($isAdminPrincipal) {
          $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE DATE_FORMAT(data, '%Y-%m') = ?");
          $stmt->bind_param("s", $mes);
          $stmt->execute();
          $receitaMesNormal = $stmt->get_result()->fetch_assoc()['total'];
          $stmt->close();
          
          $stmt = $conn->prepare("
            SELECT COALESCE(SUM(valor), 0) as total 
            FROM mensalidades 
            WHERE status = 'pago' 
            AND DATE_FORMAT(COALESCE(data_pagamento, data_vencimento), '%Y-%m') = ?
          ");
          $stmt->bind_param("s", $mes);
          $stmt->execute();
          $receitaMesMensalidades = $stmt->get_result()->fetch_assoc()['total'];
          $stmt->close();
        } else {
          $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE club_id = ? AND DATE_FORMAT(data, '%Y-%m') = ?");
          $stmt->bind_param("is", $user_club_id, $mes);
          $stmt->execute();
          $receitaMesNormal = $stmt->get_result()->fetch_assoc()['total'];
          $stmt->close();
          
          $stmt = $conn->prepare("
            SELECT COALESCE(SUM(m.valor), 0) as total 
            FROM mensalidades m 
            INNER JOIN players p ON m.jogador_id = p.id 
            INNER JOIN teams t ON p.team_id = t.id 
            WHERE m.status = 'pago' 
            AND t.club_id = ? 
            AND DATE_FORMAT(COALESCE(m.data_pagamento, m.data_vencimento), '%Y-%m') = ?
          ");
          $stmt->bind_param("is", $user_club_id, $mes);
          $stmt->execute();
          $receitaMesMensalidades = $stmt->get_result()->fetch_assoc()['total'];
          $stmt->close();
        }
      } else {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE DATE_FORMAT(data, '%Y-%m') = ?");
        $stmt->bind_param("s", $mes);
        $stmt->execute();
        $receitaMesNormal = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        $receitaMesMensalidades = 0;
      }
      
      $receitaMes = $receitaMesNormal + $receitaMesMensalidades;
      
      // DESPESAS DO MÊS
      if ($hasClubColumn) {
        if ($isAdminPrincipal) {
          $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas WHERE DATE_FORMAT(data, '%Y-%m') = ?");
          $stmt->bind_param("s", $mes);
        } else {
          $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas WHERE club_id = ? AND DATE_FORMAT(data, '%Y-%m') = ?");
          $stmt->bind_param("is", $user_club_id, $mes);
        }
      } else {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM despesas WHERE DATE_FORMAT(data, '%Y-%m') = ?");
        $stmt->bind_param("s", $mes);
      }
      
      $stmt->execute();
      $despesaMes = $stmt->get_result()->fetch_assoc()['total'];
      $stmt->close();
      
      $receitasMensais[] = floatval($receitaMes);
      $despesasMensais[] = floatval($despesaMes);
      $lucroMensal[] = floatval($receitaMes) - floatval($despesaMes);
    }
  } catch (Exception $e) {
    for ($i = 0; $i < 6; $i++) {
      $receitasMensais[] = 0;
      $despesasMensais[] = 0;
      $lucroMensal[] = 0;
    }
  }
}

$ASSET_BASE = '';
?>

<!DOCTYPE html>
<html lang="pt">
<?php require('includes/head.php'); ?>
<head>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background-color: #ffffff;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  overflow-x: hidden;
  max-width: 100%;
}

.main-content {
  margin-left: 260px;
  margin-top: 0;
  padding: 24px;
  min-height: 100vh;
  width: calc(100% - 260px);
  box-sizing: border-box;
  overflow-x: hidden;
  transition: margin-left 0.3s ease;
}

.welcome-section {
  margin-bottom: 30px;
  background: #ffffff;
  padding: 40px 30px;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.welcome-section h1 {
  font-size: 42px;
  font-weight: 700;
  color: #000000;
  margin: 0 0 12px 0;
}

.welcome-section p {
  font-size: 18px;
  color: #666666;
  margin: 0;
}

.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  font-weight: 500;
}

.alert-error {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

.stats-wrapper {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #ffffff;
}

.stat-icon.blue { background: linear-gradient(135deg, #b2c4f7ff 0%, #ffffffff 100%); }
.stat-icon.green { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
.stat-icon.purple { background: linear-gradient(135deg, #d6bceeff 0%, #ffffffff 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #ffb95dff 0%, #ffffffff 100%); }
.stat-icon.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

.chart-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  grid-column: span 2;
  grid-row: span 2;
}

.chart-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.chart-title {
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.chart-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #64748b;
}

.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.legend-dot.green { background: #22c55e; }
.legend-dot.red { background: #ef4444; }
.legend-dot.blue { background: #3b82f6; }

.section-title {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 20px 0;
}

.modules-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  margin-bottom: 40px;
}

.module-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  text-decoration: none;
  transition: all 0.3s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.module-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
  border-color: #cbd5e1;
}

.module-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: #ffffff;
  flex-shrink: 0;
}

.module-content h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.module-content p {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

@media (min-width: 640px) {
  .stats-wrapper {
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
  }
  
  .modules-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
  }
  
  .chart-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
  }
}

@media (min-width: 1024px) {
  .stats-wrapper {
    grid-template-columns: repeat(4, 1fr);
  }
  
  .modules-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }
}

@media (min-width: 1280px) {
  .modules-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

canvas {
  max-width: 100%;
  height: auto !important;
}

/* ========================================
   RESPONSIVE DESIGN
   ======================================== */

/* Tablet */
@media (max-width: 1024px) {
  .main-content {
    margin-left: 0;
    width: 100%;
    padding: 16px;
  }

  .welcome-section {
    padding: 30px 20px;
  }

  .welcome-section h1 {
    font-size: 32px;
  }

  .welcome-section p {
    font-size: 16px;
  }

  .stats-wrapper {
    grid-template-columns: repeat(2, 1fr);
  }

  .modules-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Mobile */
@media (max-width: 768px) {
  .main-content {
    margin-left: 0;
    width: 100%;
    padding: 12px;
  }

  .welcome-section {
    padding: 24px 16px;
  }

  .welcome-section h1 {
    font-size: 28px;
  }

  .welcome-section p {
    font-size: 14px;
  }

  .stats-wrapper {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .modules-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .stat-card {
    padding: 20px;
  }

  .module-card {
    padding: 16px;
  }

  .chart-container {
    padding: 16px;
  }
}

/* Small Mobile */
@media (max-width: 480px) {
  .main-content {
    margin-left: 0;
    width: 100%;
    padding: 12px;
  }

  .welcome-section {
    padding: 20px 12px;
  }

  .welcome-section h1 {
    font-size: 24px;
  }

  .stat-card {
    padding: 16px;
  }

  .module-card {
    padding: 12px;
  }

  .module-icon {
    width: 48px;
    height: 48px;
    font-size: 24px;
  }
}
</style>
</head>
<body>

  <?php require('includes/sidebar.php'); ?>
  
  <div class="main-content">
    
    <?php if (isset($_SESSION['erro'])): ?>
      <div class="alert alert-error">
        <i class='bx bx-error-circle'></i>
        <?= $_SESSION['erro'] ?>
      </div>
      <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <div class="welcome-section">
      <h1>Bem-vindo, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>! 👋</h1>
      <p>Gerencie seu clube de forma profissional e eficiente</p>
    </div>

    <div class="stats-wrapper">
      <?php if ($canViewTeamModules): ?>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon blue">
            <i class='bx bx-run'></i>
          </div>
        </div>
        <div class="stat-value">0</div>
        <div class="stat-label">Jogadores Ativos</div>
      </div>
      <?php endif; ?>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon green">
            <i class='bx bx-football'></i>
          </div>
        </div>
        <div class="stat-value">0</div>
        <div class="stat-label">Jogos Agendados</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon purple">
            <i class='bx bx-calendar'></i>
          </div>
        </div>
        <div class="stat-value">0</div>
        <div class="stat-label">Treinos Esta Semana</div>
      </div>

      <?php if ($isAdminPrincipal): ?>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon orange">
            <i class='bx bx-group'></i>
          </div>
        </div>
        <div class="stat-value"><?= $totalEquipas ?></div>
        <div class="stat-label">Equipas Registadas</div>
      </div>
      <?php endif; ?>

      <?php if ($canViewFinancial): ?>
      <div class="chart-card">
        <div class="chart-header">
          <div>
            <h3 class="chart-title">Resumo Financeiro</h3>
            <p class="chart-subtitle">Últimos 6 meses - Receitas, Despesas e Lucro</p>
          </div>
          <div class="chart-legend">
            <div class="legend-item">
              <span class="legend-dot green"></span>
              <span>Receitas</span>
            </div>
            <div class="legend-item">
              <span class="legend-dot red"></span>
              <span>Despesas</span>
            </div>
            <div class="legend-item">
              <span class="legend-dot blue"></span>
              <span>Lucro</span>
            </div>
          </div>
        </div>
        <canvas id="financialChart"></canvas>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon green">
            <i class='bx bx-trending-up'></i>
          </div>
        </div>
        <div class="stat-value"><?= number_format($totalReceitas, 0, ',', '.') ?> €</div>
        <div class="stat-label">Total Receitas</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon red">
            <i class='bx bx-trending-down'></i>
          </div>
        </div>
        <div class="stat-value"><?= number_format($totalDespesas, 0, ',', '.') ?> €</div>
        <div class="stat-label">Total Despesas</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon <?= $saldo >= 0 ? 'blue' : 'red' ?>">
            <i class='bx <?= $saldo >= 0 ? 'bx-wallet' : 'bx-wallet-alt' ?>'></i>
          </div>
        </div>
        <div class="stat-value" style="color: <?= $saldo >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= number_format($saldo, 0, ',', '.') ?> €
        </div>
        <div class="stat-label">Saldo Atual</div>
      </div>
      <?php endif; ?>
    </div>

    <h3 class="section-title">Acesso Rápido</h3>
    <div class="modules-grid">

      <?php if ($isAdminPrincipal): ?>
        <!-- Superadmin vê apenas: Equipas, Staff, Épocas, Clubes -->

        <a href="modules/equipas/listar.php" class="module-card">
          <div class="module-icon green">
            <i class='bx bx-group'></i>
          </div>
          <div class="module-content">
            <h3>Equipas</h3>
            <p>Organizar equipas técnicas</p>
          </div>
        </a>

        <a href="modules/staff/listar.php" class="module-card">
          <div class="module-icon blue">
            <i class='bx bx-briefcase'></i>
          </div>
          <div class="module-content">
            <h3>Staff</h3>
            <p>Equipa técnica e suporte</p>
          </div>
        </a>

        <a href="modules/epoca/listar.php" class="module-card">
          <div class="module-icon purple">
            <i class='bx bx-trophy'></i>
          </div>
          <div class="module-content">
            <h3>Épocas</h3>
            <p>Gestão de temporadas</p>
          </div>
        </a>

        <a href="modules/clubes/listar.php" class="module-card">
          <div class="module-icon orange">
            <i class='bx bx-building-house'></i>
          </div>
          <div class="module-content">
            <h3>Clubes</h3>
            <p>Gestão de clubes</p>
          </div>
        </a>

      <?php else: ?>
        <!-- Todos os outros utilizadores veem os módulos conforme permissões -->

        <a href="modules/jogos/listar.php" class="module-card">
          <div class="module-icon purple">
            <i class='bx bx-football'></i>
          </div>
          <div class="module-content">
            <h3>Jogos</h3>
            <p>Calendário de jogos</p>
          </div>
        </a>

        <a href="modules/treinos/listar.php" class="module-card">
          <div class="module-icon orange">
            <i class='bx bx-calendar'></i>
          </div>
          <div class="module-content">
            <h3>Treinos</h3>
            <p>Planeamento de treinos</p>
          </div>
        </a>

        <a href="modules/staff/listar.php" class="module-card">
          <div class="module-icon blue">
            <i class='bx bx-briefcase'></i>
          </div>
          <div class="module-content">
            <h3>Staff</h3>
            <p>Equipa técnica e suporte</p>
          </div>
        </a>

        <?php if ($canViewAllModules): ?>
        <a href="modules/epoca/listar.php" class="module-card">
          <div class="module-icon purple">
            <i class='bx bx-trophy'></i>
          </div>
          <div class="module-content">
            <h3>Épocas</h3>
            <p>Gestão de temporadas</p>
          </div>
        </a>
        <?php endif; ?>

        <?php if ($canViewTeamModules): ?>
        <a href="modules/jogadores/listar.php" class="module-card">
          <div class="module-icon blue">
            <i class='bx bx-run'></i>
          </div>
          <div class="module-content">
            <h3>Jogadores</h3>
            <p>Gerir plantel e atletas</p>
          </div>
        </a>

        <a href="modules/equipas/listar.php" class="module-card">
          <div class="module-icon green">
            <i class='bx bx-group'></i>
          </div>
          <div class="module-content">
            <h3>Equipas</h3>
            <p>Organizar equipas técnicas</p>
          </div>
        </a>
        <?php endif; ?>

        <?php if ($canViewFinancial): ?>
        <a href="modules/despesas/listar.php" class="module-card">
          <div class="module-icon red">
            <i class='bx bx-trending-down'></i>
          </div>
          <div class="module-content">
            <h3>Despesas</h3>
            <p>Gestão de despesas</p>
          </div>
        </a>

        <a href="modules/lucro/listar.php" class="module-card">
          <div class="module-icon green">
            <i class='bx bx-trending-up'></i>
          </div>
          <div class="module-content">
            <h3>Receitas</h3>
            <p>Gestão de receitas</p>
          </div>
        </a>
        <?php endif; ?>

      <?php endif; ?>

    </div>

  </div>

  <?php require('includes/footer.php');?>
      
  <?php if ($canViewFinancial): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const mesesLabels = <?= json_encode($meses) ?>;
    const receitasData = <?= json_encode($receitasMensais) ?>;
    const despesasData = <?= json_encode($despesasMensais) ?>;
    const lucroData = <?= json_encode($lucroMensal) ?>;

    const ctx = document.getElementById('financialChart');
    
    if (ctx) {
      const isMobile = window.innerWidth < 640;
      
      const financialChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
          labels: mesesLabels,
          datasets: [
            {
              label: 'Receitas',
              data: receitasData,
              borderColor: '#22c55e',
              backgroundColor: 'rgba(34, 197, 94, 0.1)',
              borderWidth: isMobile ? 2 : 3,
              tension: 0.4,
              fill: true,
              pointRadius: isMobile ? 3 : 5,
              pointHoverRadius: isMobile ? 5 : 7,
              pointBackgroundColor: '#22c55e',
              pointBorderColor: '#fff',
              pointBorderWidth: 2
            },
            {
              label: 'Despesas',
              data: despesasData,
              borderColor: '#ef4444',
              backgroundColor: 'rgba(239, 68, 68, 0.1)',
              borderWidth: isMobile ? 2 : 3,
              tension: 0.4,
              fill: true,
              pointRadius: isMobile ? 3 : 5,
              pointHoverRadius: isMobile ? 5 : 7,
              pointBackgroundColor: '#ef4444',
              pointBorderColor: '#fff',
              pointBorderWidth: 2
            },
            {
              label: 'Lucro',
              data: lucroData,
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              borderWidth: isMobile ? 2 : 3,
              tension: 0.4,
              fill: true,
              pointRadius: isMobile ? 3 : 5,
              pointHoverRadius: isMobile ? 5 : 7,
              pointBackgroundColor: '#3b82f6',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              borderDash: [8, 4]
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          aspectRatio: isMobile ? 1.2 : 2.5,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#0f172a',
              bodyColor: '#0f172a',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: isMobile ? 8 : 12,
              boxPadding: 6,
              usePointStyle: true,
              titleFont: {
                size: isMobile ? 11 : 13
              },
              bodyFont: {
                size: isMobile ? 10 : 12
              },
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  label += context.parsed.y.toLocaleString('pt-PT') + ' €';
                  return label;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: '#f1f5f9',
                drawBorder: false
              },
              ticks: {
                color: '#64748b',
                font: {
                  size: isMobile ? 9 : 11
                },
                maxTicksLimit: isMobile ? 5 : 8,
                callback: function(value) {
                  if (value >= 1000000) {
                    return (value / 1000000).toFixed(1) + 'M';
                  } else if (value >= 1000) {
                    return (value / 1000).toFixed(0) + 'k';
                  }
                  return value.toLocaleString('pt-PT');
                }
              }
            },
            x: {
              grid: {
                display: false,
                drawBorder: false
              },
              ticks: {
                color: '#64748b',
                font: {
                  size: isMobile ? 9 : 11
                },
                maxRotation: isMobile ? 45 : 0,
                minRotation: isMobile ? 45 : 0
              }
            }
          },
          interaction: {
            mode: 'index',
            intersect: false
          }
        }
      });
      
      // Reajustar gráfico no resize
      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
          financialChart.resize();
        }, 250);
      });
    }
  </script>
  <?php endif; ?>

  <script>
    setTimeout(() => {
      document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
      });
    }, 5000);
  </script>
  
</body>
</html>