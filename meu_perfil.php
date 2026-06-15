<?php
session_start();
// REMOVER DEPOIS DE CORRIGIR!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require('config/db.php');

// Verifica se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$sucesso = '';
$erro = '';

// Buscar dados completos do utilizador
// Buscar dados completos do utilizador
$stmt = $conn->prepare("
    SELECT u.*, c.nome as clube_nome, c.logo as clube_logo 
    FROM users u 
    LEFT JOIN clubs c ON u.club_id = c.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Calcular idade se tiver data de nascimento válida
$idade = null;
if (isset($user['data_nascimento']) && !empty($user['data_nascimento']) && $user['data_nascimento'] !== '0000-00-00') {
    try {
        $nascimento = new DateTime($user['data_nascimento']);
        $hoje = new DateTime();
        $idade = $hoje->diff($nascimento)->y;
    } catch (Exception $e) {
        $idade = null;
    }
}

// Processar atualização do perfil
// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    // Converter strings vazias para NULL
    $phone = !empty($phone) ? $phone : null;
    $address = !empty($address) ? $address : null;
    $city = !empty($city) ? $city : null;
    $postal_code = !empty($postal_code) ? $postal_code : null;
    $data_nascimento = !empty($data_nascimento) ? $data_nascimento : null;
    $avatar_url = !empty($avatar_url) ? $avatar_url : null;
    
    if (empty($full_name) || empty($email)) {
        $erro = "Nome e email são obrigatórios!";
    } else {
        try {
            // Verificar se email já existe (exceto o próprio user)
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $erro = "❌ Este email já está em uso!";
            } else {
                // Preparar update COM ou SEM password
                if (!empty($new_password)) {
                    // COM PASSWORD - 10 placeholders (?)
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET 
                            full_name = ?, 
                            email = ?, 
                            phone = ?, 
                            address = ?, 
                            city = ?, 
                            postal_code = ?, 
                            data_nascimento = ?, 
                            avatar_url = ?,
                            password_hash = ?
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    // 10 variáveis: s s s s s s s s s i
                    $stmt->bind_param("sssssssssi", 
                        $full_name, 
                        $email, 
                        $phone, 
                        $address, 
                        $city, 
                        $postal_code, 
                        $data_nascimento, 
                        $avatar_url, 
                        $password_hash, 
                        $user_id
                    );
                } else {
                    // SEM PASSWORD - 9 placeholders (?)
                    $sql = "UPDATE users SET 
                            full_name = ?, 
                            email = ?, 
                            phone = ?, 
                            address = ?, 
                            city = ?, 
                            postal_code = ?, 
                            data_nascimento = ?, 
                            avatar_url = ?
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    // 9 variáveis: s s s s s s s s i
                    $stmt->bind_param("ssssssssi", 
                        $full_name, 
                        $email, 
                        $phone, 
                        $address, 
                        $city, 
                        $postal_code, 
                        $data_nascimento, 
                        $avatar_url, 
                        $user_id
                    );
                }
                
                if ($stmt->execute()) {
                    // Atualizar sessão
                    $_SESSION['user_name'] = $full_name;
                    
                    header("Location: definicoes.php?sucesso=1");
                    exit;
                } else {
                    $erro = "❌ Erro ao atualizar: " . $stmt->error;
                }
            }
        } catch (Exception $e) {
            $erro = "❌ Erro no servidor: " . $e->getMessage();
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = "✅ Perfil atualizado com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meu Perfil - SportGes</title>
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
    max-width: 1400px;
}

.page-header {
    margin-bottom: 32px;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    color: #64748b;
}

.breadcrumb a {
    color: #3b82f6;
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb a:hover {
    color: #2563eb;
}

.page-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}

.page-title p {
    color: #64748b;
    font-size: 15px;
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #ecfdf5;
    border-left: 4px solid #10b981;
    color: #065f46;
}

.alert-error {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.profile-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* SIDEBAR DO PERFIL */
.profile-sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.profile-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}

.profile-header-card {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    padding: 32px;
    text-align: center;
    position: relative;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 48px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    margin: 0 auto 16px;
}

.profile-name {
    color: white;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.profile-role {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    padding: 6px 16px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: inline-block;
    font-weight: 600;
}

.profile-info {
    padding: 24px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #f8fafc;
    border-radius: 10px;
    margin-bottom: 12px;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item i {
    font-size: 20px;
    color: #3b82f6;
    min-width: 24px;
}

.info-item-content {
    flex: 1;
}

.info-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 2px;
}

.info-value {
    color: #0f172a;
    font-size: 14px;
    font-weight: 500;
}

.stats-card {
    padding: 24px;
}

.stats-title {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stats-title i {
    color: #3b82f6;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
}

.stat-value {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

/* ÁREA PRINCIPAL */
.profile-main {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    padding: 32px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f1f5f9;
}

.section-title i {
    color: #3b82f6;
    font-size: 24px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-top: 24px;
}

.form-grid-full {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    font-size: 14px;
    color: #334155;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.required {
    color: #ef4444;
}

.form-group input,
.form-group select {
    padding: 14px 16px;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.2s ease;
    font-family: inherit;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
}

.form-group small {
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.form-actions {
    display: flex;
    gap: 16px;
    padding-top: 32px;
    border-top: 2px solid #e2e8f0;
    margin-top: 32px;
}

.btn {
    padding: 16px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    justify-content: center;
}

.btn i {
    font-size: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    flex: 1;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59,130,246,0.4);
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
    min-width: 160px;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.club-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 12px;
    border: 2px solid #bae6fd;
}

.club-logo {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
    background: white;
}

.club-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: #0c4a6e;
    margin-bottom: 2px;
}

.club-info p {
    font-size: 13px;
    color: #0369a1;
}

@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .content-area {
        margin-left: 0;
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<?php require('includes/sidebar.php'); ?>

<div class="main-wrapper">
    <div class="content-area">
        <div class="page-header">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class='bx bx-chevron-right'></i>
                <span>Meu Perfil</span>
            </div>
            <div class="page-title">
                <h1>👤 Meu Perfil</h1>
                <p>Gerencie suas informações pessoais e configurações de conta</p>
            </div>
        </div>

        <?php if(!empty($sucesso)): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle' style="font-size: 20px;"></i>
                <?= $sucesso ?>
            </div>
        <?php elseif(!empty($erro)): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle' style="font-size: 20px;"></i>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- SIDEBAR -->
            <div class="profile-sidebar">
                <!-- CARD DO PERFIL -->
                <div class="profile-card">
                    <div class="profile-header-card">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                        <span class="profile-role"><?= htmlspecialchars($user['role']) ?></span>
                    </div>
                    <div class="profile-info">
                        <div class="info-item">
                            <i class='bx bx-envelope'></i>
                            <div class="info-item-content">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($user['phone'])): ?>
                        <div class="info-item">
                            <i class='bx bx-phone'></i>
                            <div class="info-item-content">
                                <div class="info-label">Telefone</div>
                                <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($idade): ?>
                        <div class="info-item">
                            <i class='bx bx-cake'></i>
                            <div class="info-item-content">
                                <div class="info-label">Idade</div>
                                <div class="info-value"><?= $idade ?> anos</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['city'])): ?>
                        <div class="info-item">
                            <i class='bx bx-map'></i>
                            <div class="info-item-content">
                                <div class="info-label">Localização</div>
                                <div class="info-value"><?= htmlspecialchars($user['city']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ESTATÍSTICAS -->
                <div class="profile-card stats-card">
                    <div class="stats-title">
                        <i class='bx bx-bar-chart'></i>
                        Estatísticas
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Conta criada</span>
                        <span class="stat-value">
    <?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A' ?>
</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Status</span>
                        <span class="stat-value" style="color: <?= $user['ativo'] ? '#10b981' : '#ef4444' ?>">
                            <?= $user['ativo'] ? '✅ Ativa' : '❌ Inativa' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ÁREA PRINCIPAL -->
            <div class="profile-main">
                <!-- CLUBE -->
                <?php if (!empty($user['clube_nome'])): ?>
                <div class="form-container">
                    <div class="section-title">
                        <i class='bx bx-shield'></i>
                        Clube Associado
                    </div>
                    <div class="club-badge">
                        <?php if (!empty($user['clube_logo']) && file_exists($user['clube_logo'])): ?>
    <img src="<?= htmlspecialchars($user['clube_logo']) ?>" 
         alt="Logo do clube" 
         class="club-logo"
         style="width:48px;height:48px;border-radius:8px;object-fit:cover;">
<?php else: ?>
    <div style="width:48px;height:48px;border-radius:8px;display:flex;
         align-items:center;justify-content:center;font-weight:700;
         color:#0369a1;background:#e0f2fe;font-size:20px;">
        <?= strtoupper(substr($user['clube_nome'], 0, 1)) ?>
    </div>
<?php endif; ?>
                        <div class="club-info">
                            <h4><?= htmlspecialchars($user['clube_nome']) ?></h4>
                            <p>Membro desde <?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A' ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FORMULÁRIO DE EDIÇÃO -->
                <div class="form-container">
                    <form method="POST" action="">
                        <div class="section-title">
                            <i class='bx bx-user-circle'></i>
                            Informações Pessoais
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group form-grid-full">
                                <label>Nome Completo <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    name="full_name" 
                                    value="<?= htmlspecialchars($user['full_name']) ?>"
                                    required
                                    placeholder="Seu nome completo"
                                >
                            </div>

                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <input 
                                    type="email" 
                                    name="email" 
                                    value="<?= htmlspecialchars($user['email']) ?>"
                                    required
                                    placeholder="seu@email.com"
                                >
                            </div>

                            <div class="form-group">
                                <label>Telefone</label>
                                <input 
                                    type="tel" 
                                    name="phone" 
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                    placeholder="+351 912 345 678"
                                >
                            </div>

                            <div class="form-group">
                                <label>Data de Nascimento</label>
                                <input 
                                    type="date" 
                                    name="data_nascimento" 
                                    value="<?= htmlspecialchars($user['data_nascimento'] ?? '') ?>"
                                >
                                <?php if ($idade): ?>
                                <small>Idade atual: <?= $idade ?> anos</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="section-title" style="margin-top: 40px;">
                            <i class='bx bx-map-pin'></i>
                            Endereço
                        </div>

                        <div class="form-grid">
                            <div class="form-group form-grid-full">
                                <label>Morada Completa</label>
                                <input 
                                    type="text" 
                                    name="address" 
                                    value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                    placeholder="Rua, número, andar..."
                                >
                            </div>

                            <div class="form-group">
                                <label>Cidade</label>
                                <input 
                                    type="text" 
                                    name="city" 
                                    value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                                    placeholder="Lisboa, Porto..."
                                >
                            </div>

                            <div class="form-group">
                                <label>Código Postal</label>
                                <input 
                                    type="text" 
                                    name="postal_code" 
                                    value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>"
                                    placeholder="1000-001"
                                    maxlength="8"
                                >
                            </div>
                        </div>

                        <div class="section-title" style="margin-top: 40px;">
                            <i class='bx bx-lock-alt'></i>
                            Alterar Password
                        </div>

                        <div class="form-grid">
                            <div class="form-group form-grid-full">
                                <label>Nova Password</label>
                                <input 
                                    type="password" 
                                    name="new_password" 
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                >
                                <small>Deixe vazio se não quiser alterar a password</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-save'></i>
                                Guardar Alterações
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class='bx bx-x'></i>
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>