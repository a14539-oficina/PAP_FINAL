<?php
session_start();
require('config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($email !== '' && $senha !== '') {
        $stmt = $conn->prepare("SELECT id, email, password_hash, full_name, role, ativo, club_id, team_id FROM users WHERE email = ? AND ativo = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($senha, $user['password_hash'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['club_id']   = $user['club_id']  ?? 0;
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['team_id']   = $user['team_id']  ?? 0;
                $_SESSION['user_role'] = $user['role']      ?? 4;
                error_log("Login bem-sucedido - User ID: " . $user['id'] . " | Role: " . $user['role'] . " | Team ID: " . ($user['team_id'] ?? 0));
                echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Email ou palavra-passe incorretos.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Email ou palavra-passe incorretos.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Preencha o email e a palavra-passe.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · SportGes</title>
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

.login-container {
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
    letter-spacing: -1px;
}

.logo-title {
    font-size: 24px;
    font-weight: 700;
    color: #111;
    letter-spacing: -0.4px;
    margin-bottom: 6px;
}

.logo-subtitle { font-size: 14px; color: #999; }

.card {
    background: #fff;
    border-radius: 20px;
    padding: 36px 32px;
    border: 1px solid #e8e8ed;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
}

.card-header { margin-bottom: 26px; }

.card-header h1 {
    font-size: 21px;
    font-weight: 600;
    color: #111;
    letter-spacing: -0.3px;
    margin-bottom: 5px;
}

.card-header p { font-size: 14px; color: #999; }

.form-group { margin-bottom: 18px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
    letter-spacing: 0.1px;
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
    transition: color 0.2s;
}

input[type="email"],
input[type="password"],
input[type="text"] {
    width: 100%;
    padding: 13px 16px 13px 44px;
    background: #fafafa;
    border: 1px solid #e2e2e6;
    border-radius: 12px;
    font-size: 15px;
    color: #111;
    font-family: inherit;
    transition: all 0.2s;
    -webkit-appearance: none;
}

input[type="email"]:focus,
input[type="password"]:focus,
input[type="text"]:focus {
    outline: none;
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,246,0.12);
}

input::placeholder { color: #c0c0c8; }

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
    line-height: 1;
}

.pw-toggle:hover { color: #555; }

.forgot-password { text-align: right; margin-top: 10px; }

.forgot-password a {
    color: #2563eb;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: opacity 0.2s;
}

.forgot-password a:hover { opacity: 0.7; }

.submit-btn {
    width: 100%;
    margin-top: 24px;
    padding: 14px;
    background: #2563eb;
    border: none;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    border-radius: 12px;
    cursor: pointer;
    letter-spacing: 0.2px;
    transition: filter 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(37,99,235,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.submit-btn:hover:not(:disabled) {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37,99,235,0.3);
}

.submit-btn:active:not(:disabled) { transform: translateY(0); }
.submit-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.submit-btn.success {
    background: #16a34a;
    box-shadow: 0 4px 16px rgba(22,163,74,0.4);
}

.spinner {
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.65s linear infinite;
    flex-shrink: 0;
}

.footer {
    text-align: center;
    margin-top: 26px;
    font-size: 12px;
    color: #aaa;
}

.footer a { color: #2563eb; text-decoration: none; }
.footer a:hover { opacity: 0.75; }

#toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #fff;
    border: 1px solid #e2e2e6;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 260px;
    max-width: 320px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    z-index: 9999;
    opacity: 0;
    transform: translateX(340px);
    transition: opacity 0.3s, transform 0.35s cubic-bezier(0.16,1,0.3,1);
    pointer-events: none;
    overflow: hidden;
}

#toast.show { opacity: 1; transform: translateX(0); pointer-events: auto; }
#toast.success { border-left: 3px solid #22c55e; }
#toast.error   { border-left: 3px solid #ef4444; }

.toast-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

#toast.success .toast-icon { background: rgba(34,197,94,0.15);  color: #22c55e; }
#toast.error   .toast-icon { background: rgba(239,68,68,0.15);   color: #ef4444; }

.toast-body { flex: 1; min-width: 0; }
.toast-title { font-size: 13px; font-weight: 600; color: #111; margin-bottom: 3px; }
.toast-msg   { font-size: 12px; color: #888; line-height: 1.4; }

.toast-progress {
    position: absolute;
    bottom: 0; left: 0;
    height: 2px; width: 100%;
    transform-origin: left;
    border-radius: 0 0 14px 14px;
    transition: transform linear;
}

#toast.success .toast-progress { background: #22c55e; }
#toast.error   .toast-progress { background: #ef4444; }

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    18%, 54%  { transform: translateX(-7px); }
    36%, 72%  { transform: translateX(7px); }
}

.shake { animation: shake 0.38s ease both; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 480px) {
    .card { padding: 30px 22px; }
    .logo-section { margin-bottom: 28px; }
    #toast { top: 12px; right: 12px; left: 12px; max-width: none; }
}
</style>
</head>
<body>

<div class="login-container">
    <div class="logo-section">
        <div class="logo-icon">SG</div>
        <h2 class="logo-title">SportGes</h2>
        <p class="logo-subtitle">Sistema de Gestão Desportiva</p>
    </div>

    <div class="card" id="card">
        <div class="card-header">
            <h1>Iniciar sessão</h1>
            <p>Aceda à sua conta para continuar</p>
        </div>

        <form id="loginForm" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <input type="email" name="email" id="email" required
                           placeholder="exemplo@email.com" autocomplete="email">
                    <i class="bx bx-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Palavra-passe</label>
                <div class="input-wrapper">
                    <input type="password" name="senha" id="senha" required
                           placeholder="••••••••" autocomplete="current-password">
                    <i class="bx bx-lock-alt input-icon"></i>
                    <button type="button" class="pw-toggle" id="togglePw" aria-label="Mostrar palavra-passe">
                        <i class="bx bx-hide" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="forgot-password">
                <a href="#" id="btnForgot">Esqueceu a palavra-passe?</a>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                Entrar
            </button>
        </form>
    </div>

    <div class="footer">
        Ainda não tem conta? <a href="registro.php">Criar conta</a>
    </div>
</div>

<!-- Toast -->
<div id="toast" role="alert" aria-live="polite">
    <div class="toast-icon" id="toastIcon"></div>
    <div class="toast-body">
        <div class="toast-title" id="toastTitle"></div>
        <div class="toast-msg"   id="toastMsg"></div>
    </div>
    <div class="toast-progress" id="toastBar"></div>
</div>

<script>
    var toastTimer = null;

    function showToast(type, title, msg, duration) {
        duration = duration || 3500;
        var el  = document.getElementById('toast');
        var bar = document.getElementById('toastBar');

        clearTimeout(toastTimer);
        el.className = '';
        bar.style.transition = 'none';
        bar.style.transform  = 'scaleX(1)';

        document.getElementById('toastIcon').innerHTML =
            type === 'success'
                ? '<i class="bx bx-check" style="font-size:18px"></i>'
                : '<i class="bx bx-x"     style="font-size:18px"></i>';
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMsg').textContent   = msg;

        el.classList.add(type, 'show');

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                bar.style.transition = 'transform ' + duration + 'ms linear';
                bar.style.transform  = 'scaleX(0)';
            });
        });

        toastTimer = setTimeout(function () {
            el.classList.remove('show');
        }, duration);
    }

    document.getElementById('togglePw').addEventListener('click', function () {
        var pw   = document.getElementById('senha');
        var icon = document.getElementById('eyeIcon');
        var show = pw.type === 'password';
        pw.type        = show ? 'text' : 'password';
        icon.className = show ? 'bx bx-show' : 'bx bx-hide';
        this.setAttribute('aria-label', show ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
    });

    document.querySelectorAll('input').forEach(function (inp) {
        inp.addEventListener('focus', function () {
            var icon = this.parentElement.querySelector('.input-icon');
            if (icon) icon.style.color = '#2563eb';
        });
        inp.addEventListener('blur', function () {
            var icon = this.parentElement.querySelector('.input-icon');
            if (icon) icon.style.color = '';
        });
    });

    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var btn      = document.getElementById('submitBtn');
        var formData = new FormData(this);
        var email    = formData.get('email').trim();
        var senha    = formData.get('senha').trim();

        if (!email || !senha) {
            var card = document.getElementById('card');
            card.classList.add('shake');
            card.addEventListener('animationend', function () { card.classList.remove('shake'); }, { once: true });
            showToast('error', 'Campos obrigatórios', 'Preencha o email e a palavra-passe.');
            return;
        }

        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner"></span> A entrar...';

        fetch(window.location.href, {
            method:  'POST',
            body:    formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                btn.classList.add('success');
                btn.innerHTML = '<i class="bx bx-check" style="font-size:18px"></i> Sessão iniciada';
                showToast('success', 'Login efetuado!', 'A redirecionar para o painel…', 2200);
                setTimeout(function () { window.location.href = 'dashboard.php'; }, 2200);
            } else {
                showToast('error', 'Erro no login', data.message);
                btn.disabled  = false;
                btn.innerHTML = 'Entrar';

                var target = data.message.toLowerCase().includes('email') || data.message.toLowerCase().includes('passe')
                    ? document.getElementById('email') : null;
                if (target) {
                    target.classList.add('shake');
                    target.addEventListener('animationend', function () { target.classList.remove('shake'); }, { once: true });
                }
            }
        })
        .catch(function () {
            showToast('error', 'Erro de ligação', 'Não foi possível contactar o servidor.');
            btn.disabled  = false;
            btn.innerHTML = 'Entrar';
        });
    });
</script>

<!-- Modal Recuperar Senha -->
<div id="modalForgot" style="
    display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4);
    backdrop-filter:blur(4px); z-index:10000;
    align-items:center; justify-content:center;">
    <div style="
        background:#fff; border-radius:20px; padding:36px 32px;
        width:100%; max-width:400px; margin:20px;
        box-shadow:0 8px 32px rgba(0,0,0,0.15);
        animation:fadeUp 0.3s cubic-bezier(0.16,1,0.3,1) both;">

        <div style="text-align:center; margin-bottom:24px;">
            <div style="
                width:52px; height:52px; background:#eff6ff; border-radius:14px;
                display:flex; align-items:center; justify-content:center;
                margin:0 auto 14px; font-size:24px; color:#2563eb;">
                <i class='bx bx-lock-open-alt'></i>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#111; margin-bottom:6px;">
                Recuperar palavra-passe
            </h2>
            <p style="font-size:13px; color:#999; line-height:1.5;">
                Introduza o seu email e receberá<br>as instruções de recuperação.
            </p>
        </div>

        <div style="margin-bottom:20px;">
            <label style="display:block; font-size:13px; font-weight:500; color:#555; margin-bottom:8px;">
                Email
            </label>
            <div style="position:relative;">
                <input type="email" id="forgotEmail" placeholder="exemplo@email.com"
                    style="width:100%; padding:13px 16px 13px 44px; background:#fafafa;
                    border:1px solid #e2e2e6; border-radius:12px; font-size:15px;
                    font-family:inherit; transition:all 0.2s; outline:none;"
                    onfocus="this.style.borderColor='#2563eb'; this.style.boxShadow='0 0 0 3px rgba(37,99,246,0.12)'"
                    onblur="this.style.borderColor='#e2e2e6'; this.style.boxShadow='none'">
                <i class='bx bx-envelope' style="
                    position:absolute; left:14px; top:50%; transform:translateY(-50%);
                    font-size:18px; color:#c0c0c8; pointer-events:none;"></i>
            </div>
        </div>

        <button id="btnForgotSubmit" onclick="submitForgot()" style="
            width:100%; padding:14px; background:#2563eb; border:none; color:#fff;
            font-size:15px; font-weight:600; font-family:inherit; border-radius:12px;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            gap:8px; transition:all 0.2s; box-shadow:0 2px 8px rgba(37,99,235,0.2);">
            <i class='bx bx-send'></i> Enviar instruções
        </button>

        <button onclick="closeForgot()" style="
            width:100%; margin-top:12px; padding:12px; background:none;
            border:1px solid #e2e2e6; color:#888; font-size:14px; font-weight:500;
            font-family:inherit; border-radius:12px; cursor:pointer; transition:all 0.2s;"
            onmouseover="this.style.background='#f5f5f7'"
            onmouseout="this.style.background='none'">
            Cancelar
        </button>
    </div>
</div>

<script>
    document.getElementById('btnForgot').addEventListener('click', function(e) {
        e.preventDefault();
        var modal = document.getElementById('modalForgot');
        modal.style.display = 'flex';
        setTimeout(function() { document.getElementById('forgotEmail').focus(); }, 100);
    });

    function closeForgot() {
        document.getElementById('modalForgot').style.display = 'none';
        document.getElementById('forgotEmail').value = '';
    }

    document.getElementById('modalForgot').addEventListener('click', function(e) {
        if (e.target === this) closeForgot();
    });

    function submitForgot() {
        var email = document.getElementById('forgotEmail').value.trim();
        if (!email || !/\S+@\S+\.\S+/.test(email)) {
            showToast('error', 'Email inválido', 'Introduza um email válido.');
            return;
        }
        var btn = document.getElementById('btnForgotSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> A enviar...';

        var formData = new FormData();
        formData.append('email', email);

        fetch('recuperar_senha.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            closeForgot();
            if (data.success) {
                showToast('success', 'Email enviado!', 'Verifique a sua caixa de correio.', 4000);
            } else {
                showToast('error', 'Erro', data.message);
            }
        })
        .catch(function() {
            showToast('error', 'Erro de ligação', 'Não foi possível contactar o servidor.');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-send"></i> Enviar instruções';
        });
    }
</script>

</body>
</html>