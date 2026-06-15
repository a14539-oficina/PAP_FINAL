<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: pagina_inicio.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel - SportGes</title>
  <link rel="stylesheet" href="public/css/style.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
      background: #f8fafc;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      width: 260px;
      background: #0f172a;
      padding: 24px 0;
      z-index: 100;
      transition: all 0.3s ease;
    }

    .logo {
      padding: 0 24px;
      margin-bottom: 40px;
    }

    .logo h1 {
      font-size: 28px;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: -0.5px;
    }

    .logo p {
      font-size: 12px;
      color: #64748b;
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .nav-menu {
      list-style: none;
    }

    .nav-item {
      margin: 4px 12px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      padding: 14px 16px;
      color: #94a3b8;
      text-decoration: none;
      border-radius: 10px;
      transition: all 0.2s ease;
      font-size: 15px;
      font-weight: 500;
    }

    .nav-link:hover {
      background: #1e293b;
      color: #ffffff;
    }

    .nav-link.active {
      background: #3b82f6;
      color: #ffffff;
    }

    .nav-link i {
      font-size: 22px;
      margin-right: 12px;
      min-width: 22px;
    }

    /* Header */
    .header {
      position: fixed;
      left: 260px;
      top: 0;
      right: 0;
      height: 70px;
      background: #ffffff;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 32px;
      z-index: 90;
    }

    .header-left h2 {
      font-size: 24px;
      font-weight: 700;
      color: #0f172a;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: #f8fafc;
      border-radius: 12px;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 16px;
    }

    .user-details {
      display: flex;
      flex-direction: column;
    }

    .user-name {
      font-size: 14px;
      font-weight: 600;
      color: #0f172a;
    }

    .user-role {
      font-size: 12px;
      color: #64748b;
    }

    .logout-btn {
      padding: 10px 20px;
      background: #0f172a;
      color: white;
      text-decoration: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s ease;
    }

    .logout-btn:hover {
      background: #1e293b;
      transform: translateY(-1px);
    }

    /* Main Content */
    .main-content {
      margin-left: 260px;
      margin-top: 70px;
      padding: 32px;
      min-height: calc(100vh - 70px);
    }

    .welcome-section {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      padding: 40px;
      border-radius: 20px;
      margin-bottom: 32px;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .welcome-section::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
      top: -100px;
      right: -100px;
    }

    .welcome-section h1 {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 8px;
      position: relative;
      z-index: 2;
    }

    .welcome-section p {
      font-size: 16px;
      color: #94a3b8;
      position: relative;
      z-index: 2;
    }

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: white;
      padding: 24px;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
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
    }

    .stat-icon.blue { background: #dbeafe; color: #3b82f6; }
    .stat-icon.green { background: #dcfce7; color: #22c55e; }
    .stat-icon.purple { background: #f3e8ff; color: #a855f7; }
    .stat-icon.orange { background: #fed7aa; color: #f97316; }

    .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 14px;
      color: #64748b;
      font-weight: 500;
    }

    /* Module Cards */
    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 20px;
    }

    .modules-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }

    .module-card {
      background: white;
      padding: 28px;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      text-decoration: none;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .module-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
      border-color: #3b82f6;
    }

    .module-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      flex-shrink: 0;
    }

    .module-content h3 {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 4px;
    }

    .module-content p {
      font-size: 13px;
      color: #64748b;
    }

    @media (max-width: 1024px) {
      .sidebar {
        width: 80px;
      }

      .sidebar .logo p,
      .nav-link span {
        display: none;
      }

      .header {
        left: 80px;
      }

      .main-content {
        margin-left: 80px;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .header {
        left: 0;
      }

      .main-content {
        margin-left: 0;
      }

      .user-details {
        display: none;
      }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <h1>SportGes</h1>
      <p>Pro Manager</p>
    </div>
    <ul class="nav-menu">
      <li class="nav-item">
        <a href="dashboard.php" class="nav-link active">
          <i class='bx bxs-dashboard'></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="modules/jogadores/listar.php" class="nav-link">
          <i class='bx bx-run'></i>
          <span>Jogadores</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="modules/equipas/listar.php" class="nav-link">
          <i class='bx bx-group'></i>
          <span>Equipas</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="modules/jogos/listar.php" class="nav-link">
          <i class='bx bx-football'></i>
          <span>Jogos</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="modules/treinos/listar.php" class="nav-link">
          <i class='bx bx-calendar'></i>
          <span>Treinos</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="modules/staff/listar.php" class="nav-link">
          <i class='bx bx-briefcase'></i>
          <span>Staff</span>
        </a>
      </li>
      <?php if($_SESSION['user_role'] === '1'): ?>
        <li>
            <a href="<?= isset($modulesPath) ? $modulesPath : '/SportGes/modules' ?>/despesas/relatorios.php"  class="<?= basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : '' ?>">
                <i class='bx bx-bar-chart-alt-2'></i>
                <span>Relatórios</span>
            </a>
        </li>
      <li class="nav-item">
        <a href="modules/clubes/listar.php" class="nav-link">
          <i class='bx bx-building'></i>
          <span>Clubes</span>
        </a>
      </li>
      <?php endif; ?>
      <li class="nav-item">
        <a href="modules/relatorios_tecnicos/listar.php" class="nav-link">
          <i class='bx bx-bar-chart-alt-2'></i>
          <span>Relatórios</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <h2>Dashboard</h2>
    </div>
    <div class="header-right">
      <div class="user-info">
        <div class="user-avatar">
          <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
        </div>
        <div class="user-details">
          <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
          <span class="user-role"><?= htmlspecialchars($_SESSION['user_role']) ?></span>
        </div>
      </div>
      <a href="logout.php" class="logout-btn">Sair</a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    
    <!-- Welcome Section -->
    <div class="welcome-section">
      <h1>Bem-vindo, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>! 👋</h1>
      <p>Gerencie seu clube de forma profissional e eficiente</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon blue">
            <i class='bx bx-run'></i>
          </div>
        </div>
        <div class="stat-value">0</div>
        <div class="stat-label">Jogadores Ativos</div>
      </div>

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

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon orange">
            <i class='bx bx-group'></i>
          </div>
        </div>
        <div class="stat-value">0</div>
        <div class="stat-label">Equipas Registadas</div>
      </div>
    </div>

    <!-- Quick Access Modules -->
    <h3 class="section-title">Acesso Rápido</h3>
    <div class="modules-grid">
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
    </div>
  </div>

</body>
</html>