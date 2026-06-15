 <?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Política de Privacidade · SportGes</title>
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

.rights-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2px;
    background: var(--border-light); border: 1px solid var(--border-light);
    border-radius: 14px; overflow: hidden; margin: 24px 0;
}
.right-item { background: var(--white); padding: 20px 22px; }
.right-item strong { display: block; font-size: 13px; font-weight: 700; margin-bottom: 4px; color: var(--text); }
.right-item span { font-size: 13px; color: var(--muted); font-weight: 300; line-height: 1.6; }

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
    .rights-grid { grid-template-columns: 1fr; }
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
        <h1>Política de Privacidade</h1>
        <p>Como recolhemos, utilizamos e protegemos os teus dados pessoais.</p>
        <p class="updated">Última atualização: 1 de junho de 2026</p>
    </div>
</div>

<div class="content">
    <hr class="divider-line">

    <h2>1. Quem somos</h2>
    <p>A SportGes é uma plataforma de gestão desportiva. Esta política descreve como tratamos os dados pessoais dos utilizadores registados na plataforma, em conformidade com o Regulamento Geral sobre a Proteção de Dados (RGPD — Regulamento UE 2016/679).</p>
    <p>Para questões relacionadas com privacidade, podes contactar-nos em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>.</p>

    <h2>2. Dados que recolhemos</h2>
    <p>Recolhemos os seguintes dados pessoais:</p>
    <ul>
        <li><strong>Dados de conta:</strong> nome, endereço de e-mail, palavra-passe (encriptada) e clube associado;</li>
        <li><strong>Dados de perfil:</strong> cargo/função dentro do clube, equipa e papel na plataforma;</li>
        <li><strong>Dados de atletas:</strong> nome, data de nascimento, posição, documentos desportivos e estado físico, inseridos pelo clube;</li>
        <li><strong>Dados financeiros:</strong> registos de mensalidades e pagamentos inseridos pelo clube;</li>
        <li><strong>Dados de utilização:</strong> acessos à plataforma, ações realizadas e logs de sessão para fins de segurança.</li>
    </ul>

    <h2>3. Finalidade do tratamento</h2>
    <p>Utilizamos os teus dados para:</p>
    <ul>
        <li>Criar e gerir a tua conta na plataforma;</li>
        <li>Fornecer as funcionalidades de gestão desportiva;</li>
        <li>Garantir a segurança da plataforma e prevenir utilizações abusivas;</li>
        <li>Enviar comunicações essenciais sobre o serviço (e.g., alterações aos termos, falhas técnicas);</li>
        <li>Melhorar a plataforma com base em dados de utilização agregados e anonimizados.</li>
    </ul>

    <h2>4. Base legal</h2>
    <p>O tratamento dos teus dados assenta nas seguintes bases legais:</p>
    <ul>
        <li><strong>Execução de contrato:</strong> para prestar o serviço que solicitaste;</li>
        <li><strong>Consentimento:</strong> para comunicações de marketing, quando aplicável;</li>
        <li><strong>Interesse legítimo:</strong> para garantir a segurança e melhorar o serviço.</li>
    </ul>

    <h2>5. Os teus direitos</h2>
    <p>Ao abrigo do RGPD, tens os seguintes direitos relativamente aos teus dados pessoais:</p>
    <div class="rights-grid">
        <div class="right-item">
            <strong>Acesso</strong>
            <span>Solicitar uma cópia dos dados que temos sobre ti.</span>
        </div>
        <div class="right-item">
            <strong>Retificação</strong>
            <span>Corrigir dados incorretos ou incompletos.</span>
        </div>
        <div class="right-item">
            <strong>Apagamento</strong>
            <span>Pedir a eliminação dos teus dados pessoais.</span>
        </div>
        <div class="right-item">
            <strong>Portabilidade</strong>
            <span>Receber os teus dados num formato estruturado e legível por máquina.</span>
        </div>
        <div class="right-item">
            <strong>Oposição</strong>
            <span>Opor-te ao tratamento para fins de marketing direto.</span>
        </div>
        <div class="right-item">
            <strong>Limitação</strong>
            <span>Solicitar a suspensão temporária do tratamento dos teus dados.</span>
        </div>
    </div>
    <p>Para exercer qualquer um destes direitos, contacta-nos em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>. Responderemos no prazo máximo de 30 dias.</p>

    <h2>6. Partilha de dados com terceiros</h2>
    <p>Não vendemos nem partilhamos os teus dados pessoais com terceiros para fins comerciais. Os dados podem ser partilhados apenas nas seguintes situações:</p>
    <ul>
        <li>Com fornecedores de infraestrutura (alojamento, base de dados) estritamente para operação do serviço e sob acordo de confidencialidade;</li>
        <li>Quando exigido por lei ou ordem judicial;</li>
        <li>Para proteger os direitos, propriedade ou segurança da SportGes ou dos seus utilizadores.</li>
    </ul>

    <h2>7. Retenção de dados</h2>
    <p>Os teus dados são conservados enquanto a tua conta estiver ativa. Após o cancelamento da conta, os dados são eliminados no prazo de 90 dias, exceto quando a retenção for exigida por obrigações legais.</p>
    <p>Os logs de segurança são conservados por um período máximo de 12 meses.</p>

    <h2>8. Segurança</h2>
    <p>Implementamos medidas técnicas e organizacionais para proteger os teus dados contra acesso não autorizado, perda ou destruição. As palavras-passe são armazenadas com hash seguro e nunca em texto simples. O acesso à base de dados é restrito a pessoal autorizado.</p>

    <h2>9. Cookies</h2>
    <p>A SportGes utiliza apenas cookies de sessão estritamente necessários para o funcionamento da plataforma (autenticação e preferências de interface). Não utilizamos cookies de rastreamento, publicidade ou análise de terceiros.</p>

    <h2>10. Transferências internacionais</h2>
    <p>Os teus dados são armazenados e tratados em servidores localizados na União Europeia. Não realizamos transferências de dados pessoais para países terceiros fora do Espaço Económico Europeu.</p>

    <h2>11. Menores</h2>
    <p>A plataforma pode conter dados de atletas menores de idade inseridos pelo clube. Esses dados são da responsabilidade do clube enquanto responsável pelo tratamento. A SportGes atua como subcontratante nesse contexto, tratando os dados apenas conforme as instruções do clube.</p>

    <h2>12. Alterações a esta política</h2>
    <p>Podemos atualizar esta política periodicamente. Quando o fizermos, atualizaremos a data no topo da página e notificaremos os utilizadores por e-mail em caso de alterações significativas.</p>

    <h2>13. Contacto e reclamações</h2>
    <p>Para questões sobre privacidade ou para exerceres os teus direitos, contacta-nos em <a href="mailto:a14539@oficina.pt">a14539@oficina.pt</a>.</p>
    <p>Se considerares que o tratamento dos teus dados viola o RGPD, tens o direito de apresentar uma reclamação à autoridade de controlo portuguesa: <strong>Comissão Nacional de Proteção de Dados (CNPD)</strong> — <a href="https://www.cnpd.pt" target="_blank">www.cnpd.pt</a>.</p>
</div>

<footer>
    <span>© 2026 SportGes · Gestão Desportiva</span>
    <span><a href="privacidade.php">Privacidade</a> · <a href="termos.php">Termos</a> · <a href="contacto.php">Contacto</a></span>
</footer>

</body>
</html>