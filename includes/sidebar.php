<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : '4';

$isSuperAdmin = ($userRole === '0');
$isAdmin      = ($userRole === '1');
$isDiretor    = ($userRole === '2');
$isTreinador  = ($userRole === '3');
$isJogador    = ($userRole === '4');

// SuperAdmin vê APENAS: Equipas, Staff, Época, Clubes
$canViewJogos      = !$isSuperAdmin;
$canViewTreinos    = !$isSuperAdmin;
$canViewFinancial  = $isAdmin;
$canViewClubes     = $isSuperAdmin;
$canViewEpoca      = $isSuperAdmin || $isAdmin;
$canViewJogadores  = !$isSuperAdmin && ($isAdmin || $isDiretor || $isTreinador);
$canViewEquipas    = $isSuperAdmin || $isAdmin || $isDiretor || $isTreinador;
$canViewStaff      = $isSuperAdmin || $isAdmin || $isDiretor || $isJogador;

$treinadorClubId = null;
if ($isTreinador && isset($_SESSION['club_id'])) {
    $treinadorClubId = intval($_SESSION['club_id']);
}

$base = '/modules';
?>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
        background: #fafafa;
        -webkit-font-smoothing: antialiased;
    }

    .sidebar {
        position: fixed; left: 0; top: 0;
        height: 100vh; width: 240px;
        background: rgba(255,255,255,0.8);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-right: 1px solid rgba(0,0,0,0.06);
        padding: 20px 0; z-index: 100;
        transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        overflow-y: auto; display: flex; flex-direction: column;
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }

    .logo { padding: 0 20px; margin-bottom: 32px; }
    .logo h1 { font-size: 22px; font-weight: 600; color: #1d1d1f; letter-spacing: -0.5px; }
    .logo p  { font-size: 11px; color: #86868b; margin-top: 2px; font-weight: 400; }

    .nav-menu { list-style: none; padding: 0 12px; flex: 1; display: flex; flex-direction: column; }
    .nav-item  { margin: 2px 0; }

    .nav-link {
        display: flex; align-items: center; padding: 8px 12px;
        color: #1d1d1f; text-decoration: none; border-radius: 8px;
        transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
        font-size: 14px; font-weight: 400;
    }
    .nav-link:hover        { background: rgba(0,0,0,0.04); }
    .nav-link.active       { background: #007aff; color: #fff; }
    .nav-link.active:hover { background: #0071e3; }
    .nav-link i    { font-size: 18px; margin-right: 10px; min-width: 18px; flex-shrink: 0; }
    .nav-link span { display: inline-block; white-space: nowrap; }

    .nav-item[style*="margin-top: auto"] {
        margin-top: auto !important;
        padding-top: 12px;
        border-top: 1px solid rgba(0,0,0,0.06);
    }

    .user-profile {
        margin: 16px 12px 12px; padding: 12px;
        background: rgba(0,0,0,0.03); border-radius: 12px;
        display: flex; align-items: center; gap: 10px;
        cursor: pointer; transition: all 0.2s;
    }
    .user-profile:hover { background: rgba(0,0,0,0.05); }
    .user-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, #007aff, #5856d6);
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 600; font-size: 14px; flex-shrink: 0;
    }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 13px; font-weight: 500; color: #1d1d1f; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 11px; color: #86868b; display: block; }

    .menu-toggle {
        position: fixed !important; bottom: 24px !important; right: 24px !important;
        width: 56px; height: 56px; background: #007aff; border: none; border-radius: 50%;
        color: white; font-size: 24px; cursor: pointer;
        box-shadow: 0 8px 24px rgba(0,122,255,0.4);
        z-index: 101 !important; display: none;
        align-items: center; justify-content: center;
        transition: transform 0.2s; touch-action: manipulation;
    }
    .menu-toggle:active    { transform: scale(0.92) !important; }
    .menu-toggle i         { transition: transform 0.3s; pointer-events: none; }
    .menu-toggle.active i  { transform: rotate(90deg); }

    .overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);
        z-index: 99; opacity: 0; visibility: hidden; transition: all 0.3s;
    }
    .overlay.show { opacity: 1; visibility: visible; }

    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); box-shadow: 4px 0 24px rgba(0,0,0,0.15); width: 280px; max-width: 85vw; }
        .sidebar.show { transform: translateX(0); }
        .menu-toggle { display: flex !important; }
        .nav-link { font-size: 15px; padding: 12px 14px; }
        .nav-link i { font-size: 20px; margin-right: 12px; }
        .user-profile { margin: 16px; padding: 14px; }
        .user-avatar { width: 40px; height: 40px; font-size: 15px; }
    }
    @media (max-width: 480px) {
        .menu-toggle { bottom: 20px !important; right: 20px !important; width: 52px; height: 52px; font-size: 22px; }
    }
    @media (max-width: 1024px) and (min-width: 769px) {
        .sidebar { width: 200px; }
        .logo h1  { font-size: 20px; }
        .nav-link { font-size: 13px; }
    }
</style>

<div class="overlay" id="overlay"></div>
<button class="menu-toggle" id="menuToggle" type="button" aria-label="Menu">
    <i class='bx bx-menu'></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <h1>SportGes</h1>
        <p>Pro Manager</p>
    </div>

    <ul class="nav-menu">

        <!-- Dashboard -->
        <li class="nav-item">
            <a href="/dashboard.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class='bx bxs-dashboard'></i><span>Dashboard</span>
            </a>
        </li>

        <!-- Jogos (não visível para SuperAdmin) -->
        <?php if ($canViewJogos): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/jogos/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/jogos/') !== false ? 'active' : '' ?>">
                <i class='bx bx-football'></i><span>Jogos</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Treinos (não visível para SuperAdmin) -->
        <?php if ($canViewTreinos): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/treinos/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/treinos/') !== false ? 'active' : '' ?>">
                <i class='bx bx-calendar'></i><span>Treinos</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Jogadores (não visível para SuperAdmin) -->
        <?php if ($canViewJogadores): ?>
        <li class="nav-item">
            <?php
                if ($isTreinador && $treinadorClubId) {
                    $jogadoresUrl = $base . '/jogadores/listar.php?club_id=' . $treinadorClubId;
                } else {
                    $jogadoresUrl = $base . '/jogadores/listar.php';
                }
            ?>
            <a href="<?= $jogadoresUrl ?>"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/jogadores/') !== false ? 'active' : '' ?>">
                <i class='bx bx-run'></i><span>Jogadores</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Equipas -->
        <?php if ($canViewEquipas): ?>
        <li class="nav-item">
            <?php
                if ($isTreinador && $treinadorClubId) {
                    $equipasUrl = $base . '/equipas/listar.php?club_id=' . $treinadorClubId;
                } else {
                    $equipasUrl = $base . '/equipas/listar.php';
                }
            ?>
            <a href="<?= $equipasUrl ?>"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/equipas/') !== false ? 'active' : '' ?>">
                <i class='bx bx-shield-quarter'></i><span>Equipas</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Staff -->
        <?php if ($canViewStaff): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/staff/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/staff/') !== false ? 'active' : '' ?>">
                <i class='bx bx-briefcase'></i><span>Staff</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Época -->
        <?php if ($canViewEpoca): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/epoca/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/epoca/') !== false ? 'active' : '' ?>">
                <i class='bx bx-calendar-event'></i><span>Época</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Clubes -->
        <?php if ($canViewClubes): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/clubes/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/clubes/') !== false ? 'active' : '' ?>">
                <i class='bx bx-building'></i><span>Clubes</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Despesas (não visível para SuperAdmin) -->
        <?php if ($canViewFinancial): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/despesas/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/despesas/') !== false ? 'active' : '' ?>">
                <i class='bx bx-wallet'></i><span>Despesas</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Receitas (não visível para SuperAdmin) -->
        <?php if ($canViewFinancial): ?>
        <li class="nav-item">
            <a href="<?= $base ?>/lucro/listar.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/lucro/') !== false ? 'active' : '' ?>">
                <i class='bx bx-line-chart'></i><span>Receitas</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Definições -->
        <li class="nav-item" style="margin-top: auto; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 12px;">
            <a href="/definicoes.php"
               class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'definicoes') !== false || basename($_SERVER['PHP_SELF']) == 'criar_utilizador.php' ? 'active' : '' ?>">
                <i class='bx bx-cog'></i><span>Definições</span>
            </a>
        </li>

        <!-- Sair -->
        <li class="nav-item" style="margin-bottom: 12px;">
            <a href="/logout.php" class="nav-link">
                <i class='bx bx-log-out'></i><span>Sair</span>
            </a>
        </li>

    </ul>

    <div class="user-profile">
        <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Utilizador') ?></span>
            <span class="user-role">
                <?php
                    switch((string)($_SESSION['user_role'] ?? '4')) {
                        case '0': echo 'Super Admin';    break;
                        case '1': echo 'Administrador'; break;
                        case '2': echo 'Diretor';       break;
                        case '3': echo 'Treinador';     break;
                        case '4': echo 'Jogador';       break;
                        default:  echo 'Utilizador';
                    }
                ?>
            </span>
        </div>
    </div>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar    = document.getElementById('sidebar');
    const overlay    = document.getElementById('overlay');

    function toggleMenu() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        menuToggle.classList.toggle('active');
    }

    if (menuToggle) menuToggle.addEventListener('click', toggleMenu);
    if (overlay)    overlay.addEventListener('click', toggleMenu);

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) toggleMenu();
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) toggleMenu();
    });

    if (menuToggle) {
        menuToggle.addEventListener('touchstart', e => {
            e.preventDefault();
            toggleMenu();
        }, { passive: false });
    }
</script>