<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contacto · SportGes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800;12..96,900&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --blue: #2563eb; --blue-dark: #1d4ed8; --blue-light: #eff6ff; --blue-mid: #bfdbfe;
    --text: #0d0d0d; --muted: #737373; --muted-light: #a3a3a3;
    --border: #e5e5e5; --border-light: #f0f0f0; --bg: #fafafa; --white: #ffffff;
    --radius: 18px; --radius-sm: 10px;
}
html { scroll-behavior: smooth; }
body { font-family: 'DM Sans', -apple-system, sans-serif; color: var(--text); background: var(--white); line-height: 1.6; overflow-x: hidden; }
nav {
    height: 68px; display: flex; align-items: center; justify-content: space-between;
    padding: 0 48px; background: rgba(255,255,255,0.92); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border-light); position: sticky; top: 0; z-index: 99;
}
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
.logo-mark {
    width: 38px; height: 38px; background: var(--blue); border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif; font-weight: 900; font-size: 13px; color: #fff; letter-spacing: -0.5px; flex-shrink: 0;
}
.logo span { font-family: 'Bricolage Grotesque', sans-serif; font-size: 18px; font-weight: 800; letter-spacing: -0.5px; }
.nav-right { display: flex; gap: 10px; align-items: center; }
.btn-out {
    border: 1.5px solid var(--border); background: transparent; color: var(--text);
    padding: 9px 20px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500;
    cursor: pointer; text-decoration: none; font-family: 'DM Sans', sans-serif; transition: border-color 0.2s, background 0.2s;
}
.btn-out:hover { background: var(--bg); border-color: #ccc; }
.btn-orange {
    background: var(--blue); border: none; color: #fff; padding: 10px 22px;
    border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer;
    text-decoration: none; font-family: 'DM Sans', sans-serif; transition: background 0.2s, transform 0.15s;
    box-shadow: 0 2px 12px rgba(37,99,235,0.28);
}
.btn-orange:hover { background: var(--blue-dark); transform: translateY(-1px); }
.page-header {
    background: var(--bg); border-bottom: 1px solid var(--border-light);
    padding: 64px 48px 56px;
}
.page-header-inner { max-width: 760px; margin: 0 auto; }
.eyebrow { font-size: 11px; font-weight: 600; color: var(--blue); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px; display: block; }
.page-header h1 {
    font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(30px, 4vw, 46px);
    font-weight: 900; letter-spacing: -2px; margin-bottom: 12px;
}
.page-header p { font-size: 15px; color: var(--muted); font-weight: 300; }
.content { max-width: 760px; margin: 0 auto; padding: 64px 48px 80px; }
.divider-line { border: none; border-top: 1px solid var(--border-light); margin: 0 0 48px; }
.contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 56px; }
.contact-card {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 32px 28px; background: var(--bg); transition: box-shadow 0.2s, transform 0.2s;
}
.contact-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.07); transform: translateY(-2px); }
.contact-card h3 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 16px; font-weight: 900; letter-spacing: -0.3px; margin-bottom: 8px; }
.contact-card p { font-size: 14px; color: var(--muted); line-height: 1.7; font-weight: 300; margin-bottom: 16px; }
.contact-card a { font-size: 14px; color: var(--blue); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
.contact-card a:hover { text-decoration: underline; }
.contact-card a::after { content: '→'; font-size: 14px; }
.form-section h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; font-weight: 900; letter-spacing: -0.5px; margin-bottom: 8px; }
.form-section p { font-size: 15px; color: var(--muted); font-weight: 300; margin-bottom: 32px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    font-size: 14px; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--white);
    transition: border-color 0.2s, box-shadow 0.2s; outline: none; resize: none;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group textarea { min-height: 140px; }
.btn-submit {
    background: var(--blue); color: #fff; border: none; padding: 14px 32px;
    border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: 'DM Sans', sans-serif; transition: background 0.2s, transform 0.2s;
    box-shadow: 0 4px 16px rgba(37,99,235,0.3);
}
.btn-submit:hover { background: var(--blue-dark); transform: translateY(-1px); }
footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 28px 48px; border-top: 1px solid var(--border-light);
    font-size: 13px; color: var(--muted-light); flex-wrap: wrap; gap: 8px;
}
footer a { color: var(--blue); text-decoration: none; }
footer a:hover { opacity: 0.75; }
@media (max-width: 768px) {
    nav { padding: 0 24px; }
    .page-header { padding: 48px 24px 40px; }
    .content { padding: 48px 24px 64px; }
    .contact-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    footer { padding: 24px; }
}
</style>
</head>
<body>

<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$sent  = isset($_GET['enviado']) && $_GET['enviado'] === '1';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome    = trim($_POST['nome']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $assunto = trim($_POST['assunto']  ?? '');
    $msg     = trim($_POST['mensagem'] ?? '');

    if (!$nome || !$email || !$assunto || !$msg) {
        $error = 'Por favor, preenche todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'O endereço de e-mail introduzido não é válido.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rodrigopacheco0205@gmail.com';
            $mail->Password   = 'jlwulnnfbxwfeqg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('rodrigopacheco0205@gmail.com', 'SportGes');
            $mail->addAddress('a14539@oficina.pt');
            $mail->addReplyTo($email, $nome);

            $mail->Subject = '[SportGes] ' . $assunto;
            $mail->Body    = "Nome: $nome\nEmail: $email\nAssunto: $assunto\n\nMensagem:\n$msg";

            $mail->send();
            header('Location: contacto.php?enviado=1');
            exit;

        } catch (Exception $e) {
            $error = 'Ocorreu um erro ao enviar. Por favor, contacta-nos diretamente em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>.';
        }
    }
}
?>

<nav>
    <a href="index.php" class="logo">
        <div class="logo-mark">SG</div>
        <span>SportGes</span>
    </a>
    <div class="nav-right">
        <a href="login.php" class="btn-out">Entrar</a>
        <a href="registro.php" class="btn-orange">Criar clube</a>
    </div>
</nav>

<div class="page-header">
    <div class="page-header-inner">
        <span class="eyebrow">Contacto</span>
        <h1>Fala connosco</h1>
        <p>Estamos aqui para ajudar. Envia-nos uma mensagem ou contacta-nos diretamente.</p>
    </div>
</div>

<div class="content">
    <hr class="divider-line">

    <div class="contact-grid">
        <div class="contact-card">
            <h3>E-mail</h3>
            <p>Para suporte, questões técnicas ou informações gerais sobre a plataforma.</p>
            <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>
        </div>
        <div class="contact-card">
            <h3>Privacidade & RGPD</h3>
            <p>Para questões sobre os teus dados pessoais ou para exercer os teus direitos ao abrigo do RGPD.</p>
            <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>
        </div>
        <div class="contact-card">
            <h3>Reportar problema</h3>
            <p>Encontraste um erro ou comportamento inesperado? Diz-nos e resolvemos o mais rapidamente possível.</p>
            <a href="mailto:a14539@oficina.pt">Enviar report</a>
        </div>
        <div class="contact-card">
            <h3>Sugestões</h3>
            <p>Tens uma ideia para melhorar o SportGes? Adoramos receber feedback da comunidade.</p>
            <a href="mailto:a14539@oficina.pt">Enviar sugestão</a>
        </div>
    </div>

    <div class="form-section">
        <h2>Envia uma mensagem</h2>
        <p>Preenche o formulário abaixo e respondemos em até 48 horas úteis.</p>

        <?php if ($sent): ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px 24px;font-size:15px;color:#15803d;font-weight:500;">
                ✓ Mensagem enviada com sucesso! Responderemos brevemente.
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;font-size:14px;color:#dc2626;margin-bottom:20px;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="contacto.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" placeholder="O teu nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="email@exemplo.pt" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="assunto">Assunto</label>
                    <select id="assunto" name="assunto" required>
                        <option value="" disabled <?= empty($_POST['assunto']) ? 'selected' : '' ?>>Seleciona um assunto...</option>
                        <option value="Suporte técnico"    <?= ($_POST['assunto'] ?? '') === 'Suporte técnico'    ? 'selected' : '' ?>>Suporte técnico</option>
                        <option value="Questão sobre conta"<?= ($_POST['assunto'] ?? '') === 'Questão sobre conta'? 'selected' : '' ?>>Questão sobre conta</option>
                        <option value="Privacidade / RGPD" <?= ($_POST['assunto'] ?? '') === 'Privacidade / RGPD' ? 'selected' : '' ?>>Privacidade / RGPD</option>
                        <option value="Reportar problema"  <?= ($_POST['assunto'] ?? '') === 'Reportar problema'  ? 'selected' : '' ?>>Reportar problema</option>
                        <option value="Sugestão"           <?= ($_POST['assunto'] ?? '') === 'Sugestão'           ? 'selected' : '' ?>>Sugestão</option>
                        <option value="Outro"              <?= ($_POST['assunto'] ?? '') === 'Outro'              ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" placeholder="Descreve a tua questão em detalhe..." required><?= htmlspecialchars($_POST['mensagem'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-submit">Enviar mensagem</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<footer>
    <span>© 2026 SportGes · Gestão Desportiva</span>
    <span><a href="privacidade.php">Privacidade</a> · <a href="termos.php">Termos</a> · <a href="contacto.php">Contacto</a></span>
</footer>

</body>
</html>
