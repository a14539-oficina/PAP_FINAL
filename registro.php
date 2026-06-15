<?php
session_start();
require('config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    $nome        = trim($_POST['nome_clube']   ?? '');
    $modalidade  = trim($_POST['modalidade']   ?? '');
    $telefone    = trim($_POST['telefone']      ?? '');
    $morada      = trim($_POST['morada']        ?? '');
    $email       = trim($_POST['email']         ?? '');
    $senha       = trim($_POST['senha']         ?? '');
    $confirma    = trim($_POST['confirma_senha'] ?? '');
    $nome_admin  = trim($_POST['nome_admin']    ?? '');

    /* ── validações ── */
    if (!$nome || !$modalidade || !$email || !$senha || !$nome_admin) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido.']);
        exit;
    }

    if (strlen($senha) < 8) {
        echo json_encode(['success' => false, 'message' => 'A palavra-passe deve ter pelo menos 8 caracteres.']);
        exit;
    }

    if ($senha !== $confirma) {
        echo json_encode(['success' => false, 'message' => 'As palavras-passe não coincidem.']);
        exit;
    }

    /* ── verificar se email já existe ── */
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Este email já está registado.']);
        exit;
    }

    /* ── tratar logo ── */
    $logo_path = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','svg'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Formato de logo inválido. Use JPG, PNG, WEBP ou SVG.']);
            exit;
        }
        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename   = 'club_' . uniqid() . '.' . $ext;
        $dest       = $upload_dir . $filename;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload do logo.']);
            exit;
        }
        $logo_path = $dest;
    }

    /* ── inserir clube ── */
    $stmt = $conn->prepare("INSERT INTO clubs (nome, telefone, cidade, email_contacto, logo, ativo, created_at) VALUES (?,?,?,?,?,1,NOW())");
    $stmt->bind_param("sssss", $nome, $telefone, $morada, $email, $logo_path);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar clube: ' . $stmt->error]);
        exit;
    }

    $club_id = $conn->insert_id;

    /* ── inserir utilizador admin ── */
    $hash  = password_hash($senha, PASSWORD_DEFAULT);
    $role  = 1; /* 1 = admin do clube */
    $stmt2 = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, club_id, ativo, created_at) VALUES (?,?,?,?,?,1,NOW())");
    $stmt2->bind_param("sssii", $nome_admin, $email, $hash, $role, $club_id);

    if (!$stmt2->execute()) {
        /* reverter clube criado */
        $conn->query("DELETE FROM clubs WHERE id = $club_id");
        echo json_encode(['success' => false, 'message' => 'Erro ao criar utilizador: ' . $stmt2->error]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Clube criado com sucesso!']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Clube · SportGes</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --blue: #2563eb;
    --blue-dark: #1d4ed8;
    --blue-light: #eff6ff;
    --text: #111;
    --muted: #6b7280;
    --border: #e5e7eb;
    --bg: #f9fafb;
    --white: #fff;
    --red: #ef4444;
    --green: #22c55e;
    --radius: 14px;
    --radius-sm: 10px;
}

html, body { min-height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif; }

body {
    background: var(--bg);
    color: var(--text);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    padding: 40px 20px 60px;
}

.page-wrap {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    animation: fadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    font-size: 14px;
    text-decoration: none;
    margin-bottom: 28px;
    transition: color 0.2s;
}

.back-link:hover { color: var(--text); }

.page-header {
    text-align: center;
    margin-bottom: 32px;
}

.logo-mark {
    width: 52px;
    height: 52px;
    background: var(--blue);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
    margin: 0 auto 14px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.4px;
    margin-bottom: 6px;
}

.page-header p {
    font-size: 14px;
    color: var(--muted);
}

.steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 32px;
}

.step {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--muted);
}

.step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--border);
    color: var(--muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    transition: all 0.3s;
    flex-shrink: 0;
}

.step.active .step-num { background: var(--blue); color: #fff; }
.step.active { color: var(--text); font-weight: 600; }
.step.done .step-num { background: var(--green); color: #fff; }
.step.done .step-num::after { content: '✓'; }

.step-line {
    width: 40px;
    height: 1px;
    background: var(--border);
    margin: 0 6px;
}

.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 36px 36px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
}

.section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

.form-step { display: none; }
.form-step.active { display: block; }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.form-group { margin-bottom: 18px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 7px;
}

label .req { color: var(--red); margin-left: 2px; }

.input-wrap { position: relative; }

.input-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 17px;
    color: #d1d5db;
    pointer-events: none;
    transition: color 0.2s;
    z-index: 1;
}

input[type="text"],
input[type="email"],
input[type="password"],
input[type="tel"],
select,
textarea {
    width: 100%;
    padding: 12px 14px 12px 42px;
    background: #fafafa;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--text);
    font-family: inherit;
    transition: all 0.2s;
    -webkit-appearance: none;
}

textarea { padding: 12px 14px; resize: vertical; min-height: 80px; }
textarea ~ .input-icon { top: 16px; transform: none; }

select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; cursor: pointer; }

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--blue);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

input:focus ~ .input-icon,
select:focus ~ .input-icon { color: var(--blue); }

input::placeholder, textarea::placeholder { color: #d1d5db; }

input.error { border-color: var(--red); }

.pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #d1d5db;
    font-size: 18px;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.2s;
    line-height: 1;
}

.pw-toggle:hover { color: var(--muted); }

.logo-upload {
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.logo-upload:hover { border-color: var(--blue); background: var(--blue-light); }
.logo-upload.has-file { border-color: var(--green); border-style: solid; background: #f0fdf4; }

.logo-upload input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
    padding: 0;
    border: none;
    background: none;
    box-shadow: none;
}

.logo-upload i { font-size: 28px; color: var(--muted); margin-bottom: 8px; display: block; }
.logo-upload span { font-size: 13px; color: var(--muted); }
.logo-upload .file-name { font-size: 13px; color: #16a34a; font-weight: 500; margin-top: 6px; display: none; }

.pw-strength {
    margin-top: 8px;
    display: flex;
    gap: 4px;
    align-items: center;
}

.pw-bar { height: 3px; flex: 1; border-radius: 2px; background: var(--border); transition: background 0.3s; }
.pw-label { font-size: 11px; color: var(--muted); min-width: 40px; text-align: right; }

.form-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.btn-back-step {
    background: none;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 11px 22px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-back-step:hover { background: var(--bg); color: var(--text); }

.btn-next-step,
.btn-submit {
    background: var(--blue);
    border: none;
    color: #fff;
    padding: 12px 28px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(37,99,235,0.2);
}

.btn-next-step:hover,
.btn-submit:hover:not(:disabled) {
    background: var(--blue-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(37,99,235,0.3);
}

.btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-submit.success { background: #16a34a; box-shadow: 0 4px 16px rgba(22,163,74,0.35); }

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.65s linear infinite;
    flex-shrink: 0;
}

.form-step { animation: fadeUp 0.3s cubic-bezier(0.16,1,0.3,1) both; }

.page-footer {
    text-align: center;
    margin-top: 24px;
    font-size: 13px;
    color: var(--muted);
}

.page-footer a { color: var(--blue); text-decoration: none; }

#toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 260px;
    max-width: 320px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    z-index: 9999;
    opacity: 0;
    transform: translateX(340px);
    transition: opacity 0.3s, transform 0.35s cubic-bezier(0.16,1,0.3,1);
    pointer-events: none;
    overflow: hidden;
}

#toast.show { opacity: 1; transform: translateX(0); pointer-events: auto; }
#toast.success { border-left: 3px solid var(--green); }
#toast.error   { border-left: 3px solid var(--red); }

.toast-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
#toast.success .toast-icon { background: rgba(34,197,94,0.12); color: var(--green); }
#toast.error   .toast-icon { background: rgba(239,68,68,0.12);  color: var(--red); }

.toast-title { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
.toast-msg   { font-size: 12px; color: var(--muted); line-height: 1.4; }

.toast-progress {
    position: absolute; bottom: 0; left: 0; height: 2px; width: 100%;
    transform-origin: left; border-radius: 0 0 14px 14px; transition: transform linear;
}

#toast.success .toast-progress { background: var(--green); }
#toast.error   .toast-progress { background: var(--red); }

#success-screen {
    display: none;
    text-align: center;
    padding: 20px 0 10px;
    animation: fadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
}

.success-icon-wrap {
    width: 72px;
    height: 72px;
    background: #f0fdf4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.success-icon-wrap i { font-size: 34px; color: #16a34a; }

#success-screen h2 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 10px;
    letter-spacing: -0.3px;
}

#success-screen p {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 8px;
    line-height: 1.6;
}

.redirect-bar-wrap {
    margin: 24px 0 6px;
    background: var(--bg);
    border-radius: 8px;
    height: 4px;
    overflow: hidden;
}

.redirect-bar {
    height: 100%;
    background: var(--blue);
    border-radius: 8px;
    width: 100%;
    transition: width linear;
}

.redirect-msg { font-size: 12px; color: var(--muted); }

.btn-login-now {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--blue);
    color: #fff;
    border: none;
    padding: 12px 28px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    margin-top: 20px;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(37,99,235,0.2);
}

.btn-login-now:hover { background: var(--blue-dark); transform: translateY(-1px); }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes spin { to { transform: rotate(360deg); } }

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60%  { transform: translateX(-7px); }
    40%, 80%  { transform: translateX(7px); }
}

.shake { animation: shake 0.35s ease both; }

@media (max-width: 540px) {
    .card { padding: 26px 20px; }
    .form-row { grid-template-columns: 1fr; }
    #toast { top: 12px; right: 12px; left: 12px; max-width: none; }
}
</style>
</head>
<body>

<div class="page-wrap">

    <a href="pagina_inicio.php" class="back-link">
        <i class='bx bx-chevron-left' style="font-size:18px"></i>
        Voltar ao início
    </a>

    <div class="page-header">
        <div class="logo-mark">SG</div>
        <h1>Criar novo clube</h1>
        <p>Preencha os dados para registar o seu clube no SportGes</p>
    </div>

    <!-- Steps indicator -->
    <div class="steps" id="steps-indicator">
        <div class="step active" id="step-ind-1">
            <div class="step-num">1</div>
            Clube
        </div>
        <div class="step-line"></div>
        <div class="step" id="step-ind-2">
            <div class="step-num">2</div>
            Administrador
        </div>
        <div class="step-line"></div>
        <div class="step" id="step-ind-3">
            <div class="step-num">3</div>
            Confirmação
        </div>
    </div>

    <div class="card">
        <form id="registerForm" novalidate enctype="multipart/form-data">

            <!-- ── STEP 1: Dados do Clube ── -->
            <div class="form-step active" id="step-1">
                <div class="section-label">Dados do Clube</div>

                <div class="form-group">
                    <label>Nome do clube <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" name="nome_clube" id="nome_clube" placeholder="Ex: Sport Lisboa e Benfica" required>
                        <i class='bx bx-shield input-icon'></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Modalidade <span class="req">*</span></label>
                        <div class="input-wrap">
                            <select name="modalidade" id="modalidade" required disabled>
                                <option value="Futebol" selected>Futebol</option>
                            </select>
                            <input type="hidden" name="modalidade" value="Futebol">
                            <i class='bx bx-trophy input-icon'></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <div class="input-wrap">
                            <input type="tel" name="telefone" id="telefone" placeholder="+351 900 000 000">
                            <i class='bx bx-phone input-icon'></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Cidade</label>
                    <div class="input-wrap">
                        <input type="text" name="morada" id="morada" placeholder="Ex: Lisboa">
                        <i class='bx bx-map input-icon'></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Logo do clube</label>
                    <div class="logo-upload" id="logo-upload-area">
                        <input type="file" name="logo" id="logo" accept="image/*">
                        <i class='bx bx-image-add'></i>
                        <span>Clique para selecionar ou arraste o logo</span>
                        <div class="file-name" id="file-name-display"></div>
                    </div>
                </div>

                <div class="form-nav" style="justify-content:flex-end">
                    <button type="button" class="btn-next-step" id="btn-step1-next">
                        Continuar <i class='bx bx-chevron-right' style="font-size:17px"></i>
                    </button>
                </div>
            </div>

            <!-- ── STEP 2: Dados do Administrador ── -->
            <div class="form-step" id="step-2">
                <div class="section-label">Dados do Administrador</div>

                <div class="form-group">
                    <label>Nome completo <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" name="nome_admin" id="nome_admin" placeholder="O seu nome completo" required>
                        <i class='bx bx-user input-icon'></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="email" name="email" id="email" placeholder="exemplo@email.com" required>
                        <i class='bx bx-envelope input-icon'></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Palavra-passe <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="senha" id="senha" placeholder="Mínimo 8 caracteres" required>
                            <i class='bx bx-lock-alt input-icon'></i>
                            <button type="button" class="pw-toggle" data-target="senha">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                        <div class="pw-strength" id="pw-strength">
                            <div class="pw-bar" id="bar1"></div>
                            <div class="pw-bar" id="bar2"></div>
                            <div class="pw-bar" id="bar3"></div>
                            <div class="pw-bar" id="bar4"></div>
                            <span class="pw-label" id="pw-label"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirmar palavra-passe <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="confirma_senha" id="confirma_senha" placeholder="Repetir palavra-passe" required>
                            <i class='bx bx-lock input-icon'></i>
                            <button type="button" class="pw-toggle" data-target="confirma_senha">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn-back-step" id="btn-step2-back">
                        <i class='bx bx-chevron-left' style="font-size:17px"></i> Voltar
                    </button>
                    <button type="button" class="btn-next-step" id="btn-step2-next">
                        Continuar <i class='bx bx-chevron-right' style="font-size:17px"></i>
                    </button>
                </div>
            </div>

            <!-- ── STEP 3: Resumo ── -->
            <div class="form-step" id="step-3">
                <div class="section-label">Confirmar dados</div>

                <div id="summary" style="background:var(--bg);border-radius:var(--radius-sm);padding:20px;margin-bottom:8px;font-size:14px;line-height:2;"></div>

                <div class="form-nav">
                    <button type="button" class="btn-back-step" id="btn-step3-back">
                        <i class='bx bx-chevron-left' style="font-size:17px"></i> Voltar
                    </button>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class='bx bx-check-circle' style="font-size:17px"></i>
                        Criar clube
                    </button>
                </div>
            </div>

        </form>

        <!-- ── SUCCESS SCREEN ── -->
        <div id="success-screen">
            <div class="success-icon-wrap">
                <i class='bx bx-check-circle'></i>
            </div>
            <h2>Clube criado com sucesso!</h2>
            <p>O seu clube foi registado no SportGes.<br>Pode agora iniciar sessão com as suas credenciais.</p>
            <div class="redirect-bar-wrap">
                <div class="redirect-bar" id="redirect-bar"></div>
            </div>
            <div class="redirect-msg">A redirecionar para o login em <strong id="countdown">5</strong>s…</div>
            <a href="login.php" class="btn-login-now">
                <i class='bx bx-log-in' style="font-size:17px"></i>
                Entrar agora
            </a>
        </div>
    </div>

    <div class="page-footer">
        Já tem conta? <a href="login.php">Iniciar sessão</a>
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
(function () {

    /* ── Toast ── */
    var toastTimer = null;
    function showToast(type, title, msg, dur) {
        dur = dur || 3500;
        var el = document.getElementById('toast');
        var bar = document.getElementById('toastBar');
        clearTimeout(toastTimer);
        el.className = '';
        bar.style.transition = 'none';
        bar.style.transform  = 'scaleX(1)';
        document.getElementById('toastIcon').innerHTML = type === 'success'
            ? '<i class="bx bx-check" style="font-size:17px"></i>'
            : '<i class="bx bx-x"     style="font-size:17px"></i>';
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMsg').textContent   = msg;
        el.classList.add(type, 'show');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                bar.style.transition = 'transform ' + dur + 'ms linear';
                bar.style.transform  = 'scaleX(0)';
            });
        });
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, dur);
    }

    /* ── Steps ── */
    var currentStep = 1;

    function goToStep(n) {
        document.querySelectorAll('.form-step').forEach(function (s) { s.classList.remove('active'); });
        document.getElementById('step-' + n).classList.add('active');

        for (var i = 1; i <= 3; i++) {
            var ind = document.getElementById('step-ind-' + i);
            ind.classList.remove('active', 'done');
            if (i < n)      ind.classList.add('done');
            else if (i === n) ind.classList.add('active');
        }

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ── Step 1 validation ── */
    document.getElementById('btn-step1-next').addEventListener('click', function () {
        var nome = document.getElementById('nome_clube').value.trim();
        var mod  = document.getElementById('modalidade').value;
        if (!nome || !mod) {
            showToast('error', 'Campos obrigatórios', 'Preencha o nome do clube e a modalidade.');
            if (!nome) document.getElementById('nome_clube').classList.add('error');
            if (!mod)  document.getElementById('modalidade').classList.add('error');
            return;
        }
        goToStep(2);
    });

    /* ── Step 2 validation ── */
    document.getElementById('btn-step2-next').addEventListener('click', function () {
        var nome   = document.getElementById('nome_admin').value.trim();
        var email  = document.getElementById('email').value.trim();
        var senha  = document.getElementById('senha').value;
        var conf   = document.getElementById('confirma_senha').value;
        var ok     = true;

        if (!nome)  { document.getElementById('nome_admin').classList.add('error'); ok = false; }
        if (!email || !/\S+@\S+\.\S+/.test(email)) { document.getElementById('email').classList.add('error'); ok = false; }
        if (senha.length < 8) { document.getElementById('senha').classList.add('error'); ok = false; }
        if (senha !== conf)   { document.getElementById('confirma_senha').classList.add('error'); ok = false; }

        if (!ok) { showToast('error', 'Dados inválidos', 'Verifique os campos assinalados.'); return; }

        /* build summary */
        var logoEl   = document.getElementById('logo');
        var logoName = logoEl.files.length ? logoEl.files[0].name : 'Sem logo';
        var html =
            '<div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px;">' +
            '<span style="color:var(--muted)">Clube</span><strong>' + esc(document.getElementById('nome_clube').value) + '</strong>' +
            '<span style="color:var(--muted)">Modalidade</span><span>' + esc(document.getElementById('modalidade').value) + '</span>' +
            '<span style="color:var(--muted)">Telefone</span><span>' + (esc(document.getElementById('telefone').value) || '—') + '</span>' +
            '<span style="color:var(--muted)">Cidade</span><span>' + (esc(document.getElementById('morada').value) || '—') + '</span>' +
            '<span style="color:var(--muted)">Administrador</span><strong>' + esc(nome) + '</strong>' +
            '<span style="color:var(--muted)">Email</span><span>' + esc(email) + '</span>' +
            '<span style="color:var(--muted)">Logo</span><span>' + esc(logoName) + '</span>' +
            '</div>';
        document.getElementById('summary').innerHTML = html;

        goToStep(3);
    });

    document.getElementById('btn-step2-back').addEventListener('click', function () { goToStep(1); });
    document.getElementById('btn-step3-back').addEventListener('click', function () { goToStep(2); });

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    /* ── Clear error on input ── */
    document.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', function () { this.classList.remove('error'); });
    });

    /* ── Password toggles ── */
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(this.dataset.target);
            var icon   = this.querySelector('i');
            var show   = target.type === 'password';
            target.type    = show ? 'text' : 'password';
            icon.className = show ? 'bx bx-show' : 'bx bx-hide';
        });
    });

    /* ── Password strength ── */
    document.getElementById('senha').addEventListener('input', function () {
        var v = this.value;
        var score = 0;
        if (v.length >= 8)  score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        var colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
        var labels = ['', 'Fraca', 'Média', 'Boa', 'Forte'];
        for (var i = 1; i <= 4; i++) {
            document.getElementById('bar' + i).style.background = i <= score ? colors[score] : 'var(--border)';
        }
        document.getElementById('pw-label').textContent = v.length > 0 ? labels[score] : '';
        document.getElementById('pw-label').style.color = colors[score];
    });

    /* ── Logo upload preview ── */
    document.getElementById('logo').addEventListener('change', function () {
        var area    = document.getElementById('logo-upload-area');
        var display = document.getElementById('file-name-display');
        if (this.files.length) {
            area.classList.add('has-file');
            area.querySelector('i').className = 'bx bx-check-circle';
            area.querySelector('i').style.color = '#16a34a';
            display.textContent = this.files[0].name;
            display.style.display = 'block';
        }
    });

    /* ── Submit ── */
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('submitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner"></span> A criar clube…';

        var formData = new FormData(this);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showSuccess();
            } else {
                showToast('error', 'Erro no registo', data.message);
                btn.disabled  = false;
                btn.innerHTML = '<i class="bx bx-check-circle" style="font-size:17px"></i> Criar clube';
            }
        })
        .catch(function () {
            showToast('error', 'Erro de ligação', 'Não foi possível contactar o servidor.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bx bx-check-circle" style="font-size:17px"></i> Criar clube';
        });
    });

    /* ── Success screen ── */
    function showSuccess() {
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('steps-indicator').style.display = 'none';
        document.getElementById('success-screen').style.display = 'block';

        var secs = 5;
        var bar  = document.getElementById('redirect-bar');
        var cd   = document.getElementById('countdown');

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                bar.style.transition = 'width ' + secs + 's linear';
                bar.style.width      = '0%';
            });
        });

        var interval = setInterval(function () {
            secs--;
            cd.textContent = secs;
            if (secs <= 0) {
                clearInterval(interval);
                window.location.href = 'login.php';
            }
        }, 1000);
    }

}());
</script>
</body>
</html>