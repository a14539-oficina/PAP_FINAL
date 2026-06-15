<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_name = $_SESSION['user_name'] ?? 'Utilizador';
$user_role = $_SESSION['user_role'] ?? '4';

// Converter role para texto
$role_text = 'Utilizador';
switch($user_role) {
    case '0':
        $role_text = 'Super Admin';
        break;
    case '1':
    case 'admin':
        $role_text = 'Administrador';
        break;
    case '2':
    case 'diretor':
        $role_text = 'Diretor';
        break;
    case '3':
    case 'treinador':
        $role_text = 'Treinador';
        break;
    case '4':
    case 'jogador':
        $role_text = 'Jogador';
        break;
}

$initials = strtoupper(substr($user_name, 0, 2));
?>

<style>
.topbar {
    position: relative;
    width: 100%;
    height: 70px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    margin-bottom: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.topbar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: #f8fafc;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.topbar-user:hover {
    background: #f1f5f9;
}

.topbar-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007aff, #5856d6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.topbar-user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.topbar-user-name {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.topbar-user-role {
    font-size: 12px;
    color: #64748b;
}

.topbar-logout {
    padding: 10px 20px;
    background: #0f172a;
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.topbar-logout:hover {
    background: #1e293b;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .topbar {
        padding: 0 16px;
    }
    
    .topbar-user-name {
        display: none;
    }
    
    .topbar-user-role {
        display: none;
    }
    
    .topbar-logout {
        padding: 8px 16px;
        font-size: 13px;
    }
}
</style>

<div class="topbar">
    <div class="topbar-left">
        <!-- Espaço para conteúdo futuro se necessário -->
    </div>
    <div class="topbar-right">
        <div class="topbar-user">
            <div class="topbar-avatar">
                <?= $initials ?>
            </div>
            <div class="topbar-user-info">
                <span class="topbar-user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="topbar-user-role"><?= htmlspecialchars($role_text) ?></span>
            </div>
        </div>
        <a href="<?= isset($logoutPath) ? $logoutPath : '/SportGes/logout.php' ?>" class="topbar-logout">
            Sair
        </a>
    </div>
</div>

