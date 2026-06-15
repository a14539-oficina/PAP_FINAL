<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Termos de Serviço · SportGes</title>
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
.page-header .updated { font-size: 12px; color: var(--muted-light); margin-top: 16px; }

.content { max-width: 760px; margin: 0 auto; padding: 64px 48px 80px; }
.content h2 {
    font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; font-weight: 900;
    letter-spacing: -0.5px; margin: 48px 0 14px; color: var(--text);
}
.content h2:first-child { margin-top: 0; }
.content p { font-size: 15px; color: var(--muted); line-height: 1.8; margin-bottom: 16px; font-weight: 300; }
.content ul { list-style: none; padding: 0; margin-bottom: 16px; }
.content ul li {
    font-size: 15px; color: var(--muted); line-height: 1.8; font-weight: 300;
    padding-left: 20px; position: relative; margin-bottom: 6px;
}
.content ul li::before { content: '–'; position: absolute; left: 0; color: var(--muted-light); }
.content a { color: var(--blue); text-decoration: none; }
.content a:hover { text-decoration: underline; }
.divider-line { border: none; border-top: 1px solid var(--border-light); margin: 0 0 48px; }

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
    footer { padding: 24px; }
}
</style>
</head>
<body>

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
        <span class="eyebrow">Legal</span>
        <h1>Termos de Serviço</h1>
        <p>Lê com atenção antes de utilizares a plataforma SportGes.</p>
        <p class="updated">Última atualização: 1 de junho de 2026</p>
    </div>
</div>

<div class="content">
    <hr class="divider-line">

    <h2>1. Aceitação dos Termos</h2>
    <p>Ao acederes ou utilizares a plataforma SportGes, concordas em ficar vinculado a estes Termos de Serviço. Se não concordares com algum dos termos, não deves utilizar a plataforma.</p>

    <h2>2. Descrição do Serviço</h2>
    <p>A SportGes é uma plataforma de gestão desportiva destinada a clubes de futebol e outras organizações desportivas. O serviço inclui funcionalidades de gestão de atletas, controlo financeiro, calendários de jogos e treinos, e relatórios estatísticos.</p>

    <h2>3. Registo e Conta</h2>
    <p>Para utilizares o SportGes, é necessário criar uma conta. Ao registares-te, comprometes-te a:</p>
    <ul>
        <li>Fornecer informações verdadeiras, precisas e completas;</li>
        <li>Manter os dados da conta atualizados;</li>
        <li>Guardar as tuas credenciais de acesso em segurança;</li>
        <li>Notificar imediatamente qualquer acesso não autorizado à tua conta;</li>
        <li>Ser o único responsável por toda a atividade realizada com a tua conta.</li>
    </ul>

    <h2>4. Utilização Aceitável</h2>
    <p>Ao utilizares a plataforma, comprometeste-te a não:</p>
    <ul>
        <li>Utilizar o serviço para fins ilegais ou não autorizados;</li>
        <li>Publicar conteúdo falso, enganoso ou ofensivo;</li>
        <li>Tentar aceder a áreas restritas da plataforma sem autorização;</li>
        <li>Interferir com o funcionamento normal da plataforma;</li>
        <li>Partilhar as tuas credenciais de acesso com terceiros não autorizados;</li>
        <li>Fazer uso da plataforma para fins comerciais sem autorização prévia.</li>
    </ul>

    <h2>5. Dados e Privacidade</h2>
    <p>A SportGes trata os teus dados pessoais de acordo com a nossa <a href="privacidade.php">Política de Privacidade</a>, em conformidade com o Regulamento Geral sobre a Proteção de Dados (RGPD). Ao utilizares a plataforma, consentes no tratamento dos teus dados conforme descrito nessa política.</p>

    <h2>6. Propriedade Intelectual</h2>
    <p>Todo o conteúdo da plataforma SportGes — incluindo código, design, logótipos, textos e funcionalidades — é propriedade exclusiva da SportGes. É proibida a reprodução, distribuição ou criação de obras derivadas sem autorização expressa.</p>
    <p>Os dados inseridos pelo teu clube (atletas, jogos, financeiro) pertencem-te. A SportGes não reivindica qualquer direito sobre os dados gerados pela tua organização.</p>

    <h2>7. Disponibilidade do Serviço</h2>
    <p>A SportGes empenha-se em manter a plataforma disponível de forma contínua, mas não garante disponibilidade ininterrupta. Podem ocorrer períodos de manutenção programada ou falhas imprevistas. Não nos responsabilizamos por perdas causadas por indisponibilidade temporária do serviço.</p>

    <h2>8. Limitação de Responsabilidade</h2>
    <p>A SportGes não é responsável por quaisquer danos diretos, indiretos ou consequentes resultantes da utilização ou impossibilidade de utilização da plataforma, incluindo perdas de dados, interrupção de atividade ou outros prejuízos comerciais.</p>

    <h2>9. Alterações aos Termos</h2>
    <p>Reservamo-nos o direito de modificar estes termos a qualquer momento. As alterações serão comunicadas por e-mail ou através de aviso na plataforma. A continuação da utilização após a publicação das alterações implica a aceitação dos novos termos.</p>

    <h2>10. Rescisão</h2>
    <p>Podes cancelar a tua conta a qualquer momento contactando-nos em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>. A SportGes reserva-se o direito de suspender ou encerrar contas que violem estes termos.</p>

    <h2>11. Lei Aplicável</h2>
    <p>Estes termos são regidos pela legislação portuguesa. Qualquer litígio será submetido à jurisdição exclusiva dos tribunais portugueses.</p>

    <h2>12. Contacto</h2>
    <p>Para questões relacionadas com estes termos, contacta-nos em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a> ou visita a nossa <a href="contacto.php">página de contacto</a>.</p>
</div>

<footer>
    <span>© 2026 SportGes · Gestão Desportiva</span>
    <span><a href="privacidade.php">Privacidade</a> · <a href="termos.php">Termos</a> · <a href="contacto.php">Contacto</a></span>
</footer>

</body>
</html>
