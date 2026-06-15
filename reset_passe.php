<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require('config/db.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$erro = '';
$sucesso = '';
$token_valido = false;
$user_id = null;

if ($token) {
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $token_valido = true;
        $user_id = $row['user_id'];
    } else {
        $erro = 'Este link expirou ou é inválido.';
    }
} else {
    $erro = 'Link inválido.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nova = trim($_POST['nova_senha'] ?? '');
    $conf = trim($_POST['confirma_senha'] ?? '');

    if (strlen($nova) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($nova !== $conf) {
        $erro = 'As palavras-passe não coincidem.';
    } else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt2->bind_param("s", $token);
            $stmt2->execute();
            $sucesso = 'Palavra-passe alterada com sucesso!';
        } else {
            $erro = 'Erro ao atualizar. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redefinir Palavra-passe · SportGes</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

html, body {
    height: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
}

body {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #f5f5f7;
    min-height: 100vh;
}

.container {
    width: 100%;
    max-width: 420px;
    animation: fadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.logo-section {
    text-align: center;
    margin-bottom: 36px;
}

.logo-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 12px;
    background: #2563eb;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 800;
    color: #fff;
}

.logo-title {
    font-size: 24px;
    font-weight: 700;
    color: #111;
    margin-bottom: 6px;
}

.logo-subtitle { font-size: 14px; color: #999; }

.card {
    background: #fff;
    border-radius: 20px;
    padding: 36px 32px;
    border: 1px solid #e8e8ed;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-header { margin-bottom: 26px; text-align: center; }

.card-header .icon {
    width: 56px;
    height: 56px;
    background: #eff6ff;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 26px;
    color: #2563eb;
}

.card-header h1 {
    font-size: 21px;
    font-weight: 700;
    color: #111;
    margin-bottom: 6px;
}

.card-header p { font-size: 14px; color: #999; }

.alert {
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.alert-success {
    background: #ecfdf5;
    border-left: 4px solid #10b981;
    color: #065f46;
}

.form-group { margin-bottom: 18px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
}

.input-wrapper { position: relative; }

.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #c0c0c8;
    pointer-events: none;
}

input[type="password"],
input[type="text"] {
    width: 100%;
    padding: 13px 44px 13px 44px;
    background: #fafafa;
    border: 1px solid #e2e2e6;
    border-radius: 12px;
    font-size: 15px;
    color: #111;
    font-family: inherit;
    transition: all 0.2s;
}

input[type="password"]:focus,
input[type="text"]:focus {
    outline: none;
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,246,0.12);
}

.pw-toggle {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #c0c0c8;
    font-size: 18px;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.2s;
}

.pw-toggle:hover { color: #555; }

.pw-strength {
    margin-top: 8px;
    display: flex;
    gap: 4px;
    align-items: center;
}

.pw-bar {
    height: 3px;
    flex: 1;
    border-radius: 2px;
    background: #e2e2e6;
    transition: background 0.3s;
}

.pw-label { font-size: 11px; color: #999; min-width: 40px; text-align: right; }

.submit-btn {
    width: 100%;
    margin-top: 8px;
    padding: 14px;
    background: #2563eb;
    border: none;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(37,99,235,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.submit-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }

.footer {
    text-align: center;
    margin-top: 24px;
    font-size: 13px;
    color: #aaa;
}

.footer a { color: #2563eb; text-decoration: none; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="container">
    <div class="logo-section">
        <div class="logo-icon">SG</div>
        <h2 class="logo-title">SportGes</h2>
        <p class="logo-subtitle">Sistema de Gestão Desportiva</p>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="icon"><i class='bx bx-lock-open-alt'></i></div>
            <h1>Nova palavra-passe</h1>
            <p>Escolha uma palavra-passe segura para a sua conta</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle' style="font-size:18px"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
            <div class="footer">
                <a href="login.php">← Voltar ao login</a>
            </div>

        <?php elseif ($sucesso): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle' style="font-size:18px"></i>
                <?= htmlspecialchars($sucesso) ?>
            </div>
            <a href="login.php" class="submit-btn" style="margin-top:16px;">
                <i class='bx bx-log-in'></i> Ir para o login
            </a>

        <?php elseif ($token_valido): ?>
            <form method="POST" action="reset_passe.php?token=<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label>Nova palavra-passe</label>
                    <div class="input-wrapper">
                        <input type="password" name="nova_senha" id="nova_senha" placeholder="Mínimo 8 caracteres" required>
                        <i class='bx bx-lock-alt input-icon'></i>
                        <button type="button" class="pw-toggle" onclick="togglePw('nova_senha', this)">
                            <i class='bx bx-hide'></i>
                        </button>
                    </div>
                    <div class="pw-strength">
                        <div class="pw-bar" id="bar1"></div>
                        <div class="pw-bar" id="bar2"></div>
                        <div class="pw-bar" id="bar3"></div>
                        <div class="pw-bar" id="bar4"></div>
                        <span class="pw-label" id="pw-label"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirmar palavra-passe</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirma_senha" id="confirma_senha" placeholder="Repetir palavra-passe" required>
                        <i class='bx bx-lock input-icon'></i>
                        <button type="button" class="pw-toggle" onclick="togglePw('confirma_senha', this)">
                            <i class='bx bx-hide'></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class='bx bx-check-circle'></i> Guardar nova palavra-passe
                </button>
            </form>

        <?php else: ?>
            <div class="footer" style="margin-top:0">
                <a href="login.php">← Voltar ao login</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <a href="login.php">← Voltar ao login</a>
    </div>
</div>

<script>
    function togglePw(id, btn) {
        var input = document.getElementById(id);
        var icon  = btn.querySelector('i');
        var show  = input.type === 'password';
        input.type     = show ? 'text' : 'password';
        icon.className = show ? 'bx bx-show' : 'bx bx-hide';
    }

    var novaSenha = document.getElementById('nova_senha');
    if (novaSenha) {
        novaSenha.addEventListener('input', function () {
            var v = this.value;
            var score = 0;
            if (v.length >= 8)           score++;
            if (/[A-Z]/.test(v))         score++;
            if (/[0-9]/.test(v))         score++;
            if (/[^A-Za-z0-9]/.test(v))  score++;

            var colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
            var labels = ['', 'Fraca', 'Média', 'Boa', 'Forte'];
            for (var i = 1; i <= 4; i++) {
                document.getElementById('bar' + i).style.background = i <= score ? colors[score] : '#e2e2e6';
            }
            document.getElementById('pw-label').textContent = v.length > 0 ? labels[score] : '';
            document.getElementById('pw-label').style.color = colors[score];
        });
    }
</script>
</body>
</html>