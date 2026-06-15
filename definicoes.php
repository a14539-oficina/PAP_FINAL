<?php
session_start();
require('config/db.php');

// Verifica se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === '1';
$isSuperAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === '0';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Definições - SportGes</title>
<link rel="stylesheet" href="public/css/style.css">
<style>  
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
    background: #f8fafc;
    color: #0f172a;
}

.main-wrapper {
    display: flex;
    min-height: 100vh;
}

.content-area {
    flex: 1;
    padding: 40px;
    margin-left: 280px;
}

.page-header {
    margin-bottom: 40px;
}

.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}

.page-header p {
    color: #64748b;
    font-size: 15px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    max-width: 1400px;
}

.setting-card {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
    border: 2px solid transparent;
}

.setting-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    border-color: #3b82f6;
}

.setting-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.setting-card:hover .setting-icon {
    transform: scale(1.1);
}

.icon-blue {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
}

.icon-green {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
}

.icon-purple {
    background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
    color: #6b21a8;
}

.icon-orange {
    background: linear-gradient(135deg, #fed7aa, #fdba74);
    color: #9a3412;
}

.icon-red {
    background: linear-gradient(135deg, #fecaca, #fca5a5);
    color: #991b1b;
}

.icon-indigo {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #3730a3;
}

.setting-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}

.setting-description {
    font-size: 14px;
    color: #64748b;
    line-height: 1.6;
}

.setting-badge {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 12px;
}

.badge-super {
    background: #dbeafe;
    color: #1e40af;
}

.section-divider {
    margin: 48px 0 32px 0;
    display: flex;
    align-items: center;
    gap: 16px;
}

.section-divider h2 {
    font-size: 20px;
    font-weight: 600;
    color: #475569;
}

.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e2e8f0;
}

@media (max-width: 768px) {
    .content-area {
        margin-left: 0;
        padding: 20px;
    }
    
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<?php require('includes/head.php'); ?>
<body>

<?php require('includes/sidebar.php'); ?>

<div class="main-wrapper">
    <div class="content-area">
        <div class="page-header">
            <h1>⚙️ Definições</h1>
            <p>Gerencie as configurações da plataforma SportGes</p>
        </div>

        <!-- Gestão de Utilizadores -->
        <div class="settings-grid">
            <?php if ($isAdmin || $isSuperAdmin): ?>
            <a href="criar_utilizador.php" class="setting-card">
                <div class="setting-title">Adicionar Utilizador</div>
                <div class="setting-description">
                    Crie novos utilizadores e defina as suas permissões de acesso à plataforma
                </div>
                <span class="setting-badge">Admin</span>
            </a>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
            <a href="listar_utilizadores.php" class="setting-card">
                <div class="setting-title">Gerir Utilizadores</div>
                <div class="setting-description">
                    Visualize, edite ou remova utilizadores existentes do sistema
                </div>
                <span class="setting-badge badge-super">Super Admin</span>
            </a>
            <?php endif; ?>

            <a href="meu_perfil.php" class="setting-card">
                <div class="setting-title">Meu Perfil</div>
                <div class="setting-description">
                    Atualize os seus dados pessoais e altere a sua senha de acesso
                </div>
            </a>
        </div>

       
</body>
</html>