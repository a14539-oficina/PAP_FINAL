<?php
session_start();
require('config/db.php');

// Verifica se está logado e é SUPER ADMIN (role = 0)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== '0') {
    header('Location: dashboard.php');
    exit;
}

$sucesso = '';
$erro = '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    header('Location: listar_utilizadores.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: listar_utilizadores.php');
    exit;
}

$clubs_query = $conn->query("SELECT id, nome FROM clubs ORDER BY nome ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $role        = intval($_POST['role'] ?? 4);
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $ativo       = intval($_POST['ativo'] ?? 1);
    $club_id     = isset($_POST['club_id']) && $_POST['club_id'] !== '' ? (int)$_POST['club_id'] : null;
    $new_pass    = trim($_POST['password'] ?? '');

    if ($full_name !== '' && $email !== '') {
        $set = [];
        $types = '';
        $params = [];

        $set[] = 'full_name=?';   $types .= 's'; $params[] = $full_name;
        $set[] = 'email=?';       $types .= 's'; $params[] = $email;
        $set[] = 'phone=?';       $types .= 's'; $params[] = $phone;
        $set[] = 'role=?';        $types .= 'i'; $params[] = $role;
        $set[] = 'address=?';     $types .= 's'; $params[] = $address;
        $set[] = 'city=?';        $types .= 's'; $params[] = $city;
        $set[] = 'postal_code=?'; $types .= 's'; $params[] = $postal_code;
        $set[] = 'ativo=?';       $types .= 'i'; $params[] = $ativo;

        if ($club_id === null) {
            $set[] = 'club_id=NULL';
        } else {
            $set[] = 'club_id=?'; $types .= 'i'; $params[] = $club_id;
        }

        if ($new_pass !== '') {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            array_unshift($set, 'password_hash=?');
            $types = 's' . $types;
            array_unshift($params, $hash);
        }

        $sql = "UPDATE users SET " . implode(', ', $set) . " WHERE id=?";
        $types .= 'i';
        $params[] = $user_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['sucesso'] = "Utilizador atualizado com sucesso!";
            header('Location: listar_utilizadores.php');
            exit;
        } else {
            $erro = "Erro ao atualizar: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $erro = "Por favor, preencha os campos obrigatórios (Nome e Email).";
    }
}

$role_names = [1 => 'Administrador', 2 => 'Diretor', 3 => 'Treinador', 4 => 'Jogador'];
$role_display = $role_names[$user['role']] ?? 'Utilizador';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Utilizador - SportGes</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
    min-height: 100vh;
}

/* WRAPPER PRINCIPAL */
.main-wrapper {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 40px;
    background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
    min-height: 100vh;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: white;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 24px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.back-link:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateX(-4px);
}

.back-link i {
    font-size: 18px;
}

.card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: slideUp 0.5s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px;
    color: white;
    position: relative;
    overflow: hidden;
}

.card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.user-info {
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    z-index: 1;
}

.user-avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 700;
    border: 4px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.user-details h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.user-details p {
    font-size: 16px;
    opacity: 0.95;
}

.alert {
    padding: 16px 20px;
    margin: 24px 40px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: #d1fae5;
    border-left: 4px solid #10b981;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.card-body {
    padding: 40px;
}

.section {
    margin-bottom: 40px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 3px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    font-size: 26px;
    color: #667eea;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
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
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    font-family: inherit;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.form-group small {
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group small i {
    font-size: 14px;
    color: #94a3b8;
}

.password-note {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 16px 20px;
    border-radius: 12px;
    font-size: 14px;
    color: #78350f;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.password-note i {
    font-size: 24px;
    flex-shrink: 0;
}

.form-actions {
    display: flex;
    gap: 16px;
    padding-top: 32px;
    border-top: 3px solid #f1f5f9;
    margin-top: 40px;
}

.btn {
    padding: 16px 32px;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
    font-family: inherit;
}

.btn i {
    font-size: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    flex: 1;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
}

.btn-primary:active {
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
    min-width: 160px;
}

.btn-secondary:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.danger-zone {
    margin-top: 40px;
    background: white;
    border-radius: 24px;
    overflow: hidden;
    border: 3px solid #fee2e2;
    box-shadow: 0 10px 40px rgba(239, 68, 68, 0.15);
}

.danger-header {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    padding: 32px 40px;
}

.danger-header h3 {
    font-size: 22px;
    font-weight: 700;
    color: #991b1b;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.danger-header h3 i {
    font-size: 28px;
}

.danger-header p {
    color: #7f1d1d;
    font-size: 14px;
}

.danger-actions {
    padding: 40px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.danger-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 28px;
    background: #fef2f2;
    border-radius: 16px;
    border: 2px solid #fee2e2;
    transition: all 0.3s ease;
}

.danger-action:hover {
    border-color: #fecaca;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
}

.danger-action-info h4 {
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 8px;
}

.danger-action-info p {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
}

.btn-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #78350f;
    border: 2px solid #fbbf24;
    padding: 14px 28px;
    font-weight: 600;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 2px solid #ef4444;
    padding: 14px 28px;
    font-weight: 600;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .main-content {
        margin-left: 0;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 20px;
    }
    
    .card-header,
    .card-body,
    .danger-actions {
        padding: 24px;
    }
    
    .user-info {
        flex-direction: column;
        text-align: center;
    }
    
    .user-details h1 {
        font-size: 24px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .danger-action {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .btn-warning,
    .btn-danger {
        width: 100%;
    }
}
</style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="container">
            <a href="listar_utilizadores.php" class="back-link">
                <i class='bx bx-arrow-back'></i>
                Voltar para Utilizadores
            </a>

            <?php if(!empty($erro)): ?>
                <div class="card">
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle' style="font-size: 20px;"></i>
                        <?= htmlspecialchars($erro) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div class="user-details">
                            <h1><?= htmlspecialchars($user['full_name']) ?></h1>
                            <p><?= htmlspecialchars($user['email']) ?> • <?= htmlspecialchars($role_display) ?></p>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST">
                        
                        <!-- INFORMAÇÕES BÁSICAS -->
                        <div class="section">
                            <div class="section-title">
                                <i class='bx bx-user'></i>
                                Informações Básicas
                            </div>
                            <div class="form-grid">
                                <div class="form-group form-grid-full">
                                    <label>
                                        Nome Completo <span class="required">*</span>
                                    </label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required placeholder="Ex: João Silva">
                                    <small><i class='bx bx-info-circle'></i> Como aparecerá no sistema</small>
                                </div>

                                <div class="form-group">
                                    <label>
                                        Email <span class="required">*</span>
                                    </label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="exemplo@email.com">
                                    <small><i class='bx bx-envelope'></i> Usado para login</small>
                                </div>

                                <div class="form-group">
                                    <label>Telefone</label>
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+351 912 345 678">
                                    <small><i class='bx bx-phone'></i> Opcional</small>
                                </div>
                            </div>
                        </div>

                        <!-- FUNÇÃO E CLUBE -->
                        <div class="section">
                            <div class="section-title">
                                <i class='bx bx-briefcase'></i>
                                Função e Clube
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>
                                        Função / Cargo <span class="required">*</span>
                                    </label>
                                    <select name="role" required>
                                        <option value="1" <?= $user['role'] == 1 ? 'selected' : '' ?>>👑 Administrador</option>
                                        <option value="2" <?= $user['role'] == 2 ? 'selected' : '' ?>>💼 Diretor</option>
                                        <option value="3" <?= $user['role'] == 3 ? 'selected' : '' ?>>🏃 Treinador</option>
                                        <option value="4" <?= $user['role'] == 4 ? 'selected' : '' ?>>⚽ Jogador</option>
                                    </select>
                                    <small><i class='bx bx-shield'></i> Define as permissões no sistema</small>
                                </div>

                                <div class="form-group">
                                    <label>Clube Associado</label>
                                    <select name="club_id">
                                        <option value="">🏢 Sem clube associado</option>
                                        <?php 
                                        if ($clubs_query && $clubs_query->num_rows > 0) {
                                            while ($club = $clubs_query->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $club['id'] ?>" <?= $user['club_id'] == $club['id'] ? 'selected' : '' ?>>
                                                🏆 <?= htmlspecialchars($club['nome']) ?>
                                            </option>
                                        <?php endwhile; } ?>
                                    </select>
                                    <small><i class='bx bx-buildings'></i> Clube de origem</small>
                                </div>
                            </div>
                        </div>

                        <!-- MORADA -->
                        <div class="section">
                            <div class="section-title">
                                <i class='bx bx-map'></i>
                                Morada
                            </div>
                            <div class="form-grid">
                                <div class="form-group form-grid-full">
                                    <label>Morada Completa</label>
                                    <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Rua, nº, andar...">
                                </div>
                                <div class="form-group">
                                    <label>Cidade</label>
                                    <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Ex: Lisboa">
                                </div>
                                <div class="form-group">
                                    <label>Código Postal</label>
                                    <input type="text" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="1000-001">
                                </div>
                            </div>
                        </div>

                        <!-- SEGURANÇA -->
                        <div class="section">
                            <div class="section-title">
                                <i class='bx bx-lock-alt'></i>
                                Segurança e Acesso
                            </div>
                            
                            <div class="password-note">
                                <i class='bx bx-info-circle'></i>
                                <div>
                                    <strong>Nota importante:</strong> Deixe o campo de password vazio se não pretende alterá-la. Ao preencher, a password atual será substituída.
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nova Password</label>
                                    <input type="password" name="password" placeholder="Deixe vazio para manter a atual" autocomplete="new-password">
                                    <small><i class='bx bx-key'></i> Mínimo 8 caracteres recomendado</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        Estado da Conta <span class="required">*</span>
                                    </label>
                                    <select name="ativo" required>
                                        <option value="1" <?= $user['ativo'] == 1 ? 'selected' : '' ?>>✅ Conta Ativa</option>
                                        <option value="0" <?= $user['ativo'] == 0 ? 'selected' : '' ?>>🔒 Conta Inativa</option>
                                    </select>
                                    <small><i class='bx bx-toggle-right'></i> Contas inativas não podem fazer login</small>
                                </div>
                            </div>
                        </div>

                        <!-- BOTÕES -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-save'></i>
                                Guardar Alterações
                            </button>
                            <a href="listar_utilizadores.php" class="btn btn-secondary">
                                <i class='bx bx-x'></i>
                                Cancelar
                            </a>
                        </div>

                    </form>
                </div>
            </div>

            <!-- ZONA DE PERIGO -->
            <?php if ($user_id != $_SESSION['user_id']): ?>
            <div class="danger-zone">
                <div class="danger-header">
                    <h3>
                        <i class='bx bx-error'></i>
                        Zona de Perigo
                    </h3>
                    <p>Ações irreversíveis que afetam permanentemente este utilizador</p>
                </div>
                <div class="danger-actions">
                    <div class="danger-action">
                        <div class="danger-action-info">
                            <h4>Desativar Utilizador</h4>
                            <p>O utilizador não conseguirá fazer login na plataforma até ser reativado manualmente por um administrador.</p>
                        </div>
                        <a href="listar_utilizadores.php?toggle=<?= $user_id ?>" 
                           class="btn btn-warning"
                           onclick="return confirm('Tem certeza que deseja <?= $user['ativo'] ? 'desativar' : 'ativar' ?> este utilizador?')">
                            <i class='bx <?= $user['ativo'] ? 'bx-toggle-right' : 'bx-toggle-left' ?>'></i>
                            <?= $user['ativo'] ? 'Desativar Conta' : 'Ativar Conta' ?>
                        </a>
                    </div>
                    <div class="danger-action">
                        <div class="danger-action-info">
                            <h4>Eliminar Utilizador</h4>
                            <p>Remove permanentemente o utilizador e todos os seus dados associados do sistema. Esta ação não pode ser desfeita!</p>
                        </div>
                        <a href="listar_utilizadores.php?eliminar=<?= $user_id ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('⚠️ ATENÇÃO!\n\nTem ABSOLUTA CERTEZA que deseja ELIMINAR este utilizador?\n\n❌ Esta ação NÃO PODE ser desfeita!\n❌ Todos os dados serão perdidos permanentemente!')">
                            <i class='bx bx-trash'></i>
                            Eliminar Permanentemente
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>