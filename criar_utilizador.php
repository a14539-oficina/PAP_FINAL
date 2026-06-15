<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require(__DIR__ . '/config/db.php');

// 🔒 VERIFICAR SE ESTÁ LOGADO
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$erro = '';
$sucesso = '';
$nome = '';
$email = '';
$role_id = 0;
$ativo = 1;
$club_id = 0;
$team_id = 0;

// 🔒 PEGAR DADOS DO ADMIN LOGADO
$logged_user_club_id = $_SESSION['club_id'] ?? null;
$logged_user_role    = $_SESSION['user_role'] ?? 4;
$is_super_admin      = ($logged_user_role == 0 && empty($logged_user_club_id));

// 📋 DEFINIR ROLES DISPONÍVEIS
$roles_lista = [
    2 => 'Diretor',
    3 => 'Treinador',
    4 => 'Jogador',
];

if ($logged_user_role == 1 && !empty($logged_user_club_id)) {
    $roles_lista = [1 => 'Administrador'] + $roles_lista;
}
if ($is_super_admin) {
    $roles_lista = [1 => 'Administrador'] + $roles_lista;
}

// 📝 PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome    = trim($_POST['nome']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $senha   = trim($_POST['senha']   ?? '');
    $role_id = intval($_POST['role']  ?? 0);
    $ativo   = intval($_POST['ativo'] ?? 1);
    $club_id = intval($_POST['club_id'] ?? 0);
    $team_id = intval($_POST['team_id'] ?? 0);

    // 🔒 Admin Normal força sempre o seu clube
    if ($logged_user_role == 1 && !empty($logged_user_club_id)) {
        $club_id = $logged_user_club_id;
    }

    // ✅ VALIDAÇÕES
    if (empty($nome)) {
        $erro = "O campo Nome é obrigatório.";
    } elseif (empty($email)) {
        $erro = "O campo Email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } elseif (empty($senha)) {
        $erro = "O campo Senha é obrigatório.";
    } elseif (strlen($senha) < 8) {
        $erro = "A senha deve ter no mínimo 8 caracteres.";
    } elseif ($role_id <= 0) {
        $erro = "Selecione um cargo válido.";
    } else {
        // Verificar se email já existe
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $erro = "Já existe um utilizador com este email.";
            $check->close();
        } else {
            $check->close();
            $password_hash = password_hash($senha, PASSWORD_DEFAULT);

            // ✅ INSERT CORRIGIDO — com else separado
            if ($club_id > 0) {
                $sql  = "INSERT INTO users (full_name, email, password_hash, role, ativo, club_id, team_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiiii", $nome, $email, $password_hash, $role_id, $ativo, $club_id, $team_id);
            } else {
                $sql  = "INSERT INTO users (full_name, email, password_hash, role, ativo) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $nome, $email, $password_hash, $role_id, $ativo);
            }

            if ($stmt->execute()) {
                $sucesso = "Utilizador criado com sucesso!";
                $nome    = '';
                $email   = '';
                $role_id = 0;
                $ativo   = 1;
                $club_id = 0;
                $team_id = 0;
            } else {
                $erro = "Erro ao criar utilizador: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// 🏆 BUSCAR CLUBES
$clubs_array       = [];
$total_clubs_in_db = 0;
$is_club_locked    = true;

try {
    $count_result = $conn->query("SELECT COUNT(*) as total FROM clubs");
    if ($count_result) {
        $total_clubs_in_db = $count_result->fetch_assoc()['total'];
        $count_result->free();
    }

    if ($is_super_admin) {
        $result = $conn->query("SELECT id, nome FROM clubs ORDER BY nome ASC");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) $clubs_array[] = $row;
            $result->free();
        }
        $is_club_locked = false;

    } elseif ($logged_user_role == 1 && !empty($logged_user_club_id)) {
        $stmt = $conn->prepare("SELECT id, nome FROM clubs WHERE id = ?");
        $stmt->bind_param("i", $logged_user_club_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $clubs_array[] = $row;
        $stmt->close();
        $is_club_locked = true;

    } else {
        if (!empty($logged_user_club_id)) {
            $stmt = $conn->prepare("SELECT id, nome FROM clubs WHERE id = ?");
            $stmt->bind_param("i", $logged_user_club_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $clubs_array[] = $row;
            $stmt->close();
        }
        $is_club_locked = true;
    }
} catch (Exception $e) {
    $erro = "Erro ao carregar clubes: " . $e->getMessage();
}

// 🏅 BUSCAR EQUIPAS
$teams_array = [];

try {
    if ($is_club_locked && !empty($logged_user_club_id)) {
        $stmt = $conn->prepare("SELECT id, nome FROM teams WHERE club_id = ? ORDER BY nome ASC");
        $stmt->bind_param("i", $logged_user_club_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $teams_array[] = $row;
        $stmt->close();
    } elseif ($is_super_admin) {
        $result = $conn->query("SELECT id, nome, club_id FROM teams ORDER BY nome ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) $teams_array[] = $row;
            $result->free();
        }
    }
} catch (Exception $e) {
    // silencioso
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Utilizador - SportGes</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8f9fa;
    color: #212529;
    line-height: 1.5;
}

.wrapper { display: flex; min-height: 100vh; }

.content {
    flex: 1;
    margin-left: 280px;
    padding: 32px 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.content-inner { width: 100%; max-width: 700px; }

.page-header { margin-bottom: 20px; text-align: center; }

.page-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.page-header h1 i { font-size: 28px; color: #1a73e8; }
.subtitle { color: #6c757d; font-size: 13px; }

.card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert i { font-size: 20px; }
.alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.alert-error   { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
.alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }

.info-box {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-left: 4px solid #1a73e8;
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-box i { font-size: 24px; color: #1a73e8; }

.info-box-text { font-size: 13px; color: #0d47a1; line-height: 1.5; }
.info-box-text strong { display: block; margin-bottom: 4px; font-size: 14px; }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.form-row.full { grid-template-columns: 1fr; }
.form-group { margin-bottom: 16px; }

label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 8px;
}

label i { font-size: 16px; color: #6c757d; }
.required { color: #dc3545; }

input, select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    transition: all 0.2s ease;
    font-family: inherit;
}

input:focus, select:focus {
    outline: none;
    border-color: #1a73e8;
    box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
}

input::placeholder { color: #adb5bd; }

select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%23495057' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 40px;
}

select:disabled, input:disabled {
    background-color: #f8f9fa;
    cursor: not-allowed;
    opacity: 0.7;
    color: #495057;
}

.section-divider { border-top: 2px solid #e9ecef; margin: 24px 0 20px 0; }

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin-bottom: 16px;
    padding-left: 12px;
    border-left: 4px solid #1a73e8;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i { font-size: 20px; color: #1a73e8; }

.locked-notice {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.locked-notice i { font-size: 20px; color: #856404; }
.locked-notice span { font-size: 13px; color: #856404; line-height: 1.5; }

.btn-primary {
    background: linear-gradient(135deg, #1a73e8 0%, #1557b0 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(26, 115, 232, 0.3);
    font-family: inherit;
}

.btn-primary:hover { filter: brightness(1.08); transform: translateY(-1px); }
.btn-primary:active { transform: translateY(0); }
.btn-primary i { font-size: 18px; }

@media (max-width: 768px) {
    .content { margin-left: 0; padding: 20px; }
    .form-row { grid-template-columns: 1fr; }
    .page-header h1 { font-size: 22px; }
    .card { padding: 20px; }
}
</style>
</head>
<body>

<?php
if (file_exists('includes/head.php'))    require('includes/head.php');
if (file_exists('includes/sidebar.php')) require('includes/sidebar.php');
?>

<div class="wrapper">
    <div class="content">
        <div class="content-inner">

            <div class="page-header">
                <h1><i class='bx bx-user-plus'></i> Criar Utilizador</h1>
                <p class="subtitle">Adicione um novo utilizador à plataforma</p>
            </div>

            <div class="card">

                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                        <i class='bx bx-check-circle'></i>
                        <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle'></i>
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_super_admin && $total_clubs_in_db == 0): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-info-circle'></i>
                        <strong>Atenção:</strong> Não existem clubes cadastrados. Crie um clube primeiro!
                    </div>
                <?php endif; ?>

                <?php if ($is_club_locked && !empty($clubs_array)): ?>
                    <div class="info-box">
                        <i class='bx bx-info-circle'></i>
                        <div class="info-box-text">
                            <strong>ℹ️ Informação</strong>
                            Os utilizadores criados serão automaticamente associados ao seu clube.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <!-- Nome -->
                    <div class="form-row full">
                        <div class="form-group">
                            <label><i class='bx bx-user'></i> Nome Completo <span class="required">*</span></label>
                            <input type="text" name="nome" required placeholder="João Silva"
                                   value="<?= htmlspecialchars($nome) ?>">
                        </div>
                    </div>

                    <!-- Email + Senha -->
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class='bx bx-envelope'></i> Email <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="nome@exemplo.com"
                                   value="<?= htmlspecialchars($email) ?>">
                        </div>
                        <div class="form-group">
                            <label><i class='bx bx-lock-alt'></i> Senha <span class="required">*</span></label>
                            <input type="password" name="senha" required placeholder="Mínimo 8 caracteres" minlength="8">
                        </div>
                    </div>

                    <!-- Cargo + Estado -->
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class='bx bx-briefcase'></i> Cargo <span class="required">*</span></label>
                            <select name="role" required>
                                <option value="">Selecione o cargo</option>
                                <?php foreach ($roles_lista as $rid => $rnome): ?>
                                    <option value="<?= $rid ?>" <?= $role_id == $rid ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rnome) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class='bx bx-toggle-right'></i> Estado</label>
                            <select name="ativo">
                                <option value="1" <?= $ativo == 1 ? 'selected' : '' ?>>✅ Ativo</option>
                                <option value="0" <?= $ativo == 0 ? 'selected' : '' ?>>⛔ Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider"></div>
                    <div class="section-title">
                        <i class='bx bx-buildings'></i> Associação ao Clube e Equipa
                    </div>

                    <!-- Clube -->
                    <div class="form-row full">
                        <div class="form-group">
                            <label>
                                <i class='bx bx-football'></i> Clube
                                <?php if ($is_super_admin && !empty($clubs_array)): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>

                            <?php if ($is_club_locked && !empty($clubs_array)): ?>
                                <input type="text"
                                       value="🏆 <?= htmlspecialchars($clubs_array[0]['nome']) ?>"
                                       disabled style="font-weight:600;">
                                <input type="hidden" name="club_id" value="<?= $clubs_array[0]['id'] ?>">
                                <div class="locked-notice">
                                    <i class='bx bx-lock-alt'></i>
                                    <span>Clube bloqueado: <strong><?= htmlspecialchars($clubs_array[0]['nome']) ?></strong></span>
                                </div>

                            <?php else: ?>
                                <select name="club_id" id="club_select"
                                        <?= ($is_super_admin && !empty($clubs_array)) ? 'required' : '' ?>>
                                    <option value="">🏢 Selecione um clube</option>
                                    <?php foreach ($clubs_array as $c): ?>
                                        <option value="<?= $c['id'] ?>"
                                                <?= $club_id == $c['id'] ? 'selected' : '' ?>>
                                            🏆 <?= htmlspecialchars($c['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if (!empty($clubs_array)): ?>
                                    <div class="info-box" style="margin-top:12px;">
                                        <i class='bx bx-check-circle'></i>
                                        <div class="info-box-text">
                                            <strong><?= count($clubs_array) ?></strong> clube(s) disponível(eis)
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Equipa -->
                    <div class="form-row full">
                        <div class="form-group">
                            <label><i class='bx bx-group'></i> Equipa</label>

                            <?php if ($is_club_locked && !empty($teams_array)): ?>
                                <!-- Admin normal: dropdown fixo com equipas do clube -->
                                <select name="team_id">
                                    <option value="">Sem equipa</option>
                                    <?php foreach ($teams_array as $t): ?>
                                        <option value="<?= $t['id'] ?>"
                                                <?= $team_id == $t['id'] ? 'selected' : '' ?>>
                                            🏅 <?= htmlspecialchars($t['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($is_club_locked && empty($teams_array)): ?>
                                <input type="text" value="Nenhuma equipa criada para este clube" disabled>
                                <div class="locked-notice" style="margin-top:12px;">
                                    <i class='bx bx-info-circle'></i>
                                    <span>Não existem equipas criadas para este clube.</span>
                                </div>

                            <?php elseif ($is_super_admin): ?>
                                <!-- Super Admin: dropdown dinâmico filtrado por clube -->
                                <select name="team_id" id="team_select">
                                    <option value="">Selecione primeiro um clube</option>
                                </select>

                            <?php else: ?>
                                <input type="text" value="Nenhuma equipa disponível" disabled>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class='bx bx-user-plus'></i> Criar Utilizador
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($is_super_admin): ?>
<script>
const allTeams = <?= json_encode($teams_array) ?>;

document.getElementById('club_select').addEventListener('change', function () {
    const clubId     = parseInt(this.value);
    const teamSelect = document.getElementById('team_select');

    teamSelect.innerHTML = '<option value="">Sem equipa</option>';

    const filtered = allTeams.filter(t => t.club_id == clubId);
    filtered.forEach(t => {
        const opt       = document.createElement('option');
        opt.value       = t.id;
        opt.textContent = '🏅 ' + t.nome;
        teamSelect.appendChild(opt);
    });

    if (filtered.length === 0) {
        const opt       = document.createElement('option');
        opt.value       = '';
        opt.textContent = 'Nenhuma equipa para este clube';
        opt.disabled    = true;
        teamSelect.appendChild(opt);
    }
});
</script>
<?php endif; ?>

</body>
</html>