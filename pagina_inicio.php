<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SportGes · Gestão Desportiva</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800;12..96,900&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --blue: #2563eb;
    --blue-dark: #1d4ed8;
    --blue-light: #eff6ff;
    --blue-mid: #bfdbfe;
    --text: #0d0d0d;
    --muted: #737373;
    --muted-light: #a3a3a3;
    --border: #e5e5e5;
    --border-light: #f0f0f0;
    --bg: #fafafa;
    --white: #ffffff;
    --radius: 18px;
    --radius-sm: 10px;
    --radius-xs: 6px;
}

html { scroll-behavior: smooth; }

body {
    font-family: 'DM Sans', -apple-system, sans-serif;
    color: var(--text);
    background: var(--white);
    line-height: 1.6;
    overflow-x: hidden;
}

/* ── NAV ── */
nav {
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 48px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border-light);
    position: sticky;
    top: 0;
    z-index: 99;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--text);
}

.logo-mark {
    width: 38px;
    height: 38px;
    background: var(--blue);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 900;
    font-size: 13px;
    color: #fff;
    letter-spacing: -0.5px;
    flex-shrink: 0;
}

.logo span {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.nav-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-out {
    border: 1.5px solid var(--border);
    background: transparent;
    color: var(--text);
    padding: 9px 20px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.2s, background 0.2s;
}

.btn-out:hover { background: var(--bg); border-color: #ccc; }

.btn-orange {
    background: var(--blue);
    border: none;
    color: #fff;
    padding: 10px 22px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    font-family: 'DM Sans', sans-serif;
    transition: background 0.2s, transform 0.15s;
    box-shadow: 0 2px 12px rgba(37,99,235,0.28);
}

.btn-orange:hover {
    background: var(--blue-dark);
    transform: translateY(-1px);
}

/* ── HERO ── */
.hero {
    background: var(--white);
    padding: 100px 48px 80px;
    display: flex;
    align-items: center;
    gap: 80px;
    max-width: 1200px;
    margin: 0 auto;
    min-height: 520px;
}

.hero-left {
    flex: 1.1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.hero-right {
    flex: 1;
}

.badge-live {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--blue-light);
    border: 1px solid var(--blue-mid);
    color: var(--blue);
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 32px;
    width: fit-content;
    letter-spacing: 0.1px;
}

.dot-live {
    width: 6px;
    height: 6px;
    background: var(--blue);
    border-radius: 50%;
    animation: blink 1.4s ease infinite;
}

.hero h1 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: clamp(36px, 4.5vw, 58px);
    font-weight: 900;
    line-height: 1.0;
    letter-spacing: -2.5px;
    color: var(--text);
    margin-bottom: 24px;
}

.hero h1 span { color: var(--blue); }

.hero-sub {
    font-size: 17px;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 44px;
    max-width: 400px;
    font-weight: 300;
}

.hero-btns {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    align-items: center;
}

.btn-hero-main {
    background: var(--blue);
    color: #fff;
    border: none;
    padding: 16px 32px;
    border-radius: var(--radius-sm);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none;
    letter-spacing: -0.2px;
    transition: background 0.2s, transform 0.2s;
    box-shadow: 0 4px 24px rgba(37,99,235,0.35);
}

.btn-hero-main:hover {
    background: var(--blue-dark);
    transform: translateY(-2px);
}

.btn-hero-ghost {
    color: var(--muted);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    font-family: 'DM Sans', sans-serif;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
}

.btn-hero-ghost:hover { color: var(--text); }
.btn-hero-ghost::after { content: '→'; font-size: 16px; }

/* Hero card visual */
.hero-visual {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.04);
}

.hero-visual svg { display: block; width: 100%; height: auto; }

/* ── DIVIDER ── */
.divider {
    border: none;
    border-top: 1px solid var(--border-light);
    margin: 0;
}

/* ── STATS ── */
.stats-wrap {
    background: var(--white);
    padding: 0 48px;
}

.stats {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    border-top: 1px solid var(--border-light);
    border-bottom: 1px solid var(--border-light);
}

.stat {
    flex: 1;
    padding: 36px 24px;
    text-align: center;
    border-right: 1px solid var(--border-light);
}

.stat:last-child { border-right: none; }

.stat-n {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 32px;
    font-weight: 900;
    color: var(--blue);
    letter-spacing: -1.5px;
    display: block;
    margin-bottom: 4px;
}

.stat-l {
    font-size: 13px;
    color: var(--muted-light);
    font-weight: 400;
}

/* ── SCENE CARDS ── */
.scenes-wrap {
    padding: 80px 48px;
    background: var(--bg);
    border-top: 1px solid var(--border-light);
    border-bottom: 1px solid var(--border-light);
}

.scenes-inner {
    max-width: 1200px;
    margin: 0 auto;
}

.scenes-header {
    margin-bottom: 48px;
}

.scenes {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}

.scene {
    position: relative;
    overflow: hidden;
    height: 260px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--white);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    transition: box-shadow 0.25s, transform 0.25s;
}

.scene:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    transform: translateY(-3px);
}

.scene-art {
    width: 100%;
    height: 100%;
    display: block;
}

.scene-label {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 100%);
}

.scene-label strong {
    display: block;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: -0.2px;
    margin-bottom: 2px;
    font-family: 'Bricolage Grotesque', sans-serif;
}

.scene-label span {
    color: rgba(255,255,255,0.6);
    font-size: 12px;
    font-weight: 300;
}

/* ── FEATURES ── */
.features-section {
    padding: 100px 48px;
    max-width: 1200px;
    margin: 0 auto;
}

.eyebrow {
    font-size: 11px;
    font-weight: 600;
    color: var(--blue);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 14px;
    display: block;
}

.sec-title {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: clamp(26px, 3vw, 38px);
    font-weight: 900;
    letter-spacing: -1.5px;
    margin-bottom: 14px;
    color: var(--text);
}

.sec-sub {
    font-size: 16px;
    color: var(--muted);
    margin-bottom: 56px;
    max-width: 440px;
    line-height: 1.7;
    font-weight: 300;
}

.grid-6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    background: var(--border-light);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    overflow: hidden;
}

.feat {
    background: var(--white);
    padding: 36px 32px;
    transition: background 0.2s;
}

.feat:hover { background: var(--bg); }

.feat-ico {
    font-size: 28px;
    margin-bottom: 18px;
    line-height: 1;
    display: block;
}

.feat h3 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 15px;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--text);
    letter-spacing: -0.3px;
}

.feat p {
    font-size: 13.5px;
    color: var(--muted);
    line-height: 1.65;
    font-weight: 300;
}

/* ── CTA BOTTOM ── */
.cta-strip {
    background: var(--blue);
    padding: 96px 48px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta-strip::before {
    content: '';
    position: absolute;
    top: -100px;
    right: -100px;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}

.cta-strip::after {
    content: '';
    position: absolute;
    bottom: -80px;
    left: -60px;
    width: 280px;
    height: 280px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}

.cta-strip h2 {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: clamp(26px, 3.5vw, 44px);
    font-weight: 900;
    color: #fff;
    letter-spacing: -1.5px;
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
}

.cta-strip p {
    font-size: 16px;
    color: rgba(255,255,255,0.7);
    margin-bottom: 36px;
    position: relative;
    z-index: 1;
    font-weight: 300;
}

.btn-white-cta {
    background: #fff;
    color: var(--blue);
    border: none;
    padding: 16px 36px;
    border-radius: var(--radius-sm);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none;
    position: relative;
    z-index: 1;
    display: inline-block;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18);
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-white-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(0,0,0,0.22);
}

/* ── FOOTER ── */
footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 28px 48px;
    border-top: 1px solid var(--border-light);
    font-size: 13px;
    color: var(--muted-light);
    flex-wrap: wrap;
    gap: 8px;
}

footer a { color: var(--blue); text-decoration: none; }
footer a:hover { opacity: 0.75; }

/* ── ANIMATIONS ── */
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.2; }
}

/* ── RESPONSIVE ── */
@media (max-width: 1024px) {
    .hero { gap: 48px; padding: 72px 32px 64px; }
}

@media (max-width: 768px) {
    nav { padding: 0 24px; }

    .hero {
        flex-direction: column;
        padding: 56px 24px 48px;
        gap: 40px;
        min-height: auto;
    }
    .hero-left { align-items: flex-start; }
    .hero h1 { font-size: 36px; }

    .stats-wrap { padding: 0 24px; }
    .stats { flex-wrap: wrap; }
    .stat { min-width: 50%; border-bottom: 1px solid var(--border-light); }

    .scenes-wrap { padding: 56px 24px; }
    .scenes { grid-template-columns: 1fr; gap: 16px; }

    .features-section { padding: 72px 24px; }
    .grid-6 { grid-template-columns: 1fr 1fr; }

    .cta-strip { padding: 64px 24px; }
    footer { padding: 24px; }
}

@media (max-width: 480px) {
    .grid-6 { grid-template-columns: 1fr; }
    .hero h1 { font-size: 32px; }
}
</style>
</head>
<body>

<!-- NAV -->
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

<!-- HERO -->
<section class="hero">
    <div class="hero-left">
        <div class="badge-live">
            <span class="dot-live"></span>
            Plataforma para clubes de futebol
        </div>
        <h1>O teu clube.<br>A tua <span>gestão.</span><br>Um só lugar.</h1>
        <p class="hero-sub">Plantel, mensalidades, convocatórias, calendário de jogos — tudo digitalizado, do infantil ao sénior.</p>
        <div class="hero-btns">
            <a href="registro.php" class="btn-hero-main">Criar clube grátis</a>
            <a href="login.php" class="btn-hero-ghost">Já tenho conta</a>
        </div>
    </div>
    <div class="hero-right">
        <div class="hero-visual">
            <svg viewBox="0 0 480 380" xmlns="http://www.w3.org/2000/svg">
                <rect width="480" height="380" fill="#1a1a1a"/>
                <!-- campo base -->
                <rect x="40" y="40" width="400" height="300" fill="#166534" rx="4"/>
                <rect x="40" y="40" width="400" height="300" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5" rx="4"/>
                <!-- linhas campo -->
                <line x1="240" y1="40" x2="240" y2="340" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
                <circle cx="240" cy="190" r="42" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
                <circle cx="240" cy="190" r="3.5" fill="rgba(255,255,255,0.2)"/>
                <rect x="40" y="140" width="70" height="100" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
                <rect x="370" y="140" width="70" height="100" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
                <rect x="40" y="163" width="28" height="54" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                <rect x="412" y="163" width="28" height="54" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>

                <!-- jogadores orange -->
                <circle cx="120" cy="155" r="20" fill="#2563eb" opacity="0.92"/>
                <text x="120" y="160" text-anchor="middle" fill="#fff" font-size="11" font-weight="800" font-family="sans-serif">10</text>
                <!-- jogadores white -->
                <circle cx="195" cy="120" r="16" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.25)" stroke-width="1"/>
                <text x="195" y="125" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="sans-serif">7</text>
                <circle cx="285" cy="128" r="16" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.25)" stroke-width="1"/>
                <text x="285" y="133" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="sans-serif">11</text>
                <circle cx="240" cy="215" r="16" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.18)" stroke-width="1"/>
                <text x="240" y="220" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="sans-serif">8</text>
                <circle cx="160" cy="262" r="16" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.14)" stroke-width="1"/>
                <text x="160" y="267" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="sans-serif">6</text>
                <circle cx="320" cy="255" r="16" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.14)" stroke-width="1"/>
                <text x="320" y="260" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="sans-serif">5</text>

                <!-- linhas de passe -->
                <line x1="120" y1="155" x2="195" y2="120" stroke="#2563eb" stroke-width="1.5" stroke-dasharray="5,4" opacity="0.6"/>
                <line x1="195" y1="120" x2="285" y2="128" stroke="rgba(255,255,255,0.18)" stroke-width="1" stroke-dasharray="5,4"/>

                <!-- card próximo jogo -->
                <rect x="24" y="258" width="168" height="72" rx="10" fill="rgba(37,99,235,0.95)"/>
                <text x="38" y="278" fill="rgba(255,255,255,0.65)" font-size="9" font-weight="700" font-family="sans-serif" letter-spacing="0.5">PRÓXIMO JOGO</text>
                <text x="38" y="298" fill="#fff" font-size="14" font-weight="900" font-family="sans-serif">CF Estrela</text>
                <text x="38" y="315" fill="rgba(255,255,255,0.65)" font-size="10" font-family="sans-serif">Sáb 10 Mai · 15:00</text>

                <!-- card plantel -->
                <rect x="300" y="42" width="138" height="72" rx="10" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
                <text x="315" y="62" fill="rgba(255,255,255,0.4)" font-size="9" font-weight="700" font-family="sans-serif" letter-spacing="0.5">PLANTEL SÉNIOR</text>
                <text x="315" y="88" fill="#fff" font-size="26" font-weight="900" font-family="sans-serif">24</text>
                <text x="350" y="88" fill="rgba(255,255,255,0.35)" font-size="12" font-family="sans-serif">atletas</text>
                <text x="315" y="104" fill="#2563eb" font-size="10" font-weight="600" font-family="sans-serif">+3 esta semana</text>
            </svg>
        </div>
    </div>
</section>

<!-- STATS -->
<div class="stats-wrap">
    <div class="stats">
        <div class="stat">
            <span class="stat-n">1.200+</span>
            <span class="stat-l">Clubes registados</span>
        </div>
        <div class="stat">
            <span class="stat-n">38k</span>
            <span class="stat-l">Atletas geridos</span>
        </div>
        <div class="stat">
            <span class="stat-n">€2M+</span>
            <span class="stat-l">Em mensalidades processadas</span>
        </div>
        <div class="stat">
            <span class="stat-n">98%</span>
            <span class="stat-l">Satisfação dos gestores</span>
        </div>
    </div>
</div>

<!-- SCENE CARDS -->
<div class="scenes-wrap">
    <div class="scenes-inner">
        <div class="scenes-header">
            <span class="eyebrow">Em ação</span>
            <h2 class="sec-title">Vê como funciona</h2>
            <p class="sec-sub" style="margin-bottom:0">Cada detalhe do teu clube, visualizado de forma clara e intuitiva.</p>
        </div>
        <div class="scenes">

            <!-- Resultado -->
            <div class="scene">
                <svg class="scene-art" viewBox="0 0 300 260" xmlns="http://www.w3.org/2000/svg">
                    <rect width="300" height="260" fill="#1a1a2e"/>
                    <rect x="0" y="100" width="300" height="160" fill="#166534"/>
                    <rect x="28" y="100" width="244" height="160" fill="#15803d"/>
                    <line x1="150" y1="100" x2="150" y2="260" stroke="rgba(255,255,255,0.22)" stroke-width="1.5"/>
                    <rect x="70" y="100" width="160" height="64" fill="none" stroke="rgba(255,255,255,0.22)" stroke-width="1.5"/>
                    <circle cx="150" cy="100" r="32" fill="none" stroke="rgba(255,255,255,0.22)" stroke-width="1.5"/>
                    <rect x="28" y="105" width="48" height="40" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
                    <rect x="224" y="105" width="48" height="40" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
                    <!-- card resultado -->
                    <rect x="14" y="14" width="130" height="64" rx="10" fill="rgba(255,255,255,0.07)" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
                    <text x="26" y="34" fill="rgba(255,255,255,0.4)" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.5">RESULTADO FINAL</text>
                    <text x="26" y="62" fill="#fff" font-size="26" font-weight="900" font-family="sans-serif">2 — 1</text>
                    <circle cx="205" cy="145" r="9" fill="#fff" opacity="0.9"/>
                    <circle cx="158" cy="178" r="7" fill="#2563eb"/>
                    <circle cx="108" cy="162" r="7" fill="#2563eb"/>
                </svg>
                <div class="scene-label">
                    <strong>Resultados em tempo real</strong>
                    <span>Regista golos, cartões e stats do jogo</span>
                </div>
            </div>

            <!-- Plantel -->
            <div class="scene">
                <svg class="scene-art" viewBox="0 0 300 260" xmlns="http://www.w3.org/2000/svg">
                    <rect width="300" height="260" fill="#0f172a"/>
                    <rect x="16" y="14" width="268" height="232" rx="12" fill="#1e293b"/>
                    <text x="32" y="40" fill="rgba(255,255,255,0.3)" font-size="9" font-weight="700" font-family="sans-serif" letter-spacing="0.5">PLANTEL · SÉNIOR A</text>
                    <rect x="32" y="48" width="236" height="1" fill="rgba(255,255,255,0.06)"/>
                    <circle cx="50" cy="74" r="13" fill="#2563eb"/>
                    <text x="50" y="79" text-anchor="middle" fill="#fff" font-size="10" font-weight="800" font-family="sans-serif">10</text>
                    <text x="72" y="70" fill="#fff" font-size="12" font-weight="700" font-family="sans-serif">Rui Gomes</text>
                    <text x="72" y="84" fill="rgba(255,255,255,0.35)" font-size="10" font-family="sans-serif">Extremo · Sub-23</text>
                    <rect x="226" y="63" width="44" height="18" rx="5" fill="rgba(34,197,94,0.14)"/>
                    <text x="248" y="75" text-anchor="middle" fill="#22c55e" font-size="9" font-weight="700" font-family="sans-serif">ATIVO</text>
                    <rect x="32" y="100" width="236" height="1" fill="rgba(255,255,255,0.06)"/>
                    <circle cx="50" cy="126" r="13" fill="#334155"/>
                    <text x="50" y="131" text-anchor="middle" fill="#fff" font-size="10" font-weight="800" font-family="sans-serif">4</text>
                    <text x="72" y="122" fill="#fff" font-size="12" font-weight="700" font-family="sans-serif">Paulo Silva</text>
                    <text x="72" y="136" fill="rgba(255,255,255,0.35)" font-size="10" font-family="sans-serif">Defesa central</text>
                    <rect x="226" y="115" width="44" height="18" rx="5" fill="rgba(37,99,235,0.14)"/>
                    <text x="248" y="127" text-anchor="middle" fill="#2563eb" font-size="9" font-weight="700" font-family="sans-serif">LESÃO</text>
                    <rect x="32" y="152" width="236" height="1" fill="rgba(255,255,255,0.06)"/>
                    <circle cx="50" cy="178" r="13" fill="#334155"/>
                    <text x="50" y="183" text-anchor="middle" fill="#fff" font-size="10" font-weight="800" font-family="sans-serif">1</text>
                    <text x="72" y="174" fill="#fff" font-size="12" font-weight="700" font-family="sans-serif">André Costa</text>
                    <text x="72" y="188" fill="rgba(255,255,255,0.35)" font-size="10" font-family="sans-serif">Guarda-redes</text>
                    <rect x="226" y="167" width="44" height="18" rx="5" fill="rgba(34,197,94,0.14)"/>
                    <text x="248" y="179" text-anchor="middle" fill="#22c55e" font-size="9" font-weight="700" font-family="sans-serif">ATIVO</text>
                </svg>
                <div class="scene-label">
                    <strong>Gestão de plantel</strong>
                    <span>Fichas, posições, estado físico e docs</span>
                </div>
            </div>

            <!-- Financeiro -->
            <div class="scene">
                <svg class="scene-art" viewBox="0 0 300 260" xmlns="http://www.w3.org/2000/svg">
                    <rect width="300" height="260" fill="#0f172a"/>
                    <rect x="16" y="14" width="268" height="232" rx="12" fill="#1e293b"/>
                    <text x="32" y="40" fill="rgba(255,255,255,0.3)" font-size="9" font-weight="700" font-family="sans-serif" letter-spacing="0.5">FINANCEIRO · MAIO 2025</text>
                    <rect x="32" y="52" width="108" height="62" rx="9" fill="rgba(37,99,235,0.14)"/>
                    <text x="46" y="72" fill="#2563eb" font-size="9" font-weight="700" font-family="sans-serif">MENSALIDADES</text>
                    <text x="46" y="100" fill="#fff" font-size="22" font-weight="900" font-family="sans-serif">3.240€</text>
                    <rect x="152" y="52" width="108" height="62" rx="9" fill="rgba(34,197,94,0.1)"/>
                    <text x="166" y="72" fill="#22c55e" font-size="9" font-weight="700" font-family="sans-serif">RECEBIDO</text>
                    <text x="166" y="100" fill="#fff" font-size="22" font-weight="900" font-family="sans-serif">2.880€</text>
                    <text x="32" y="138" fill="rgba(255,255,255,0.3)" font-size="9" font-weight="700" font-family="sans-serif">EM ATRASO</text>
                    <rect x="32" y="145" width="194" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
                    <rect x="32" y="145" width="138" height="8" rx="4" fill="#2563eb" opacity="0.65"/>
                    <text x="32" y="178" fill="rgba(255,255,255,0.3)" font-size="9" font-weight="700" font-family="sans-serif">ATLETAS EM DIA</text>
                    <rect x="32" y="185" width="194" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
                    <rect x="32" y="185" width="168" height="8" rx="4" fill="#22c55e" opacity="0.65"/>
                    <text x="32" y="218" fill="rgba(255,255,255,0.3)" font-size="9" font-weight="700" font-family="sans-serif">PRÓXIMO VENCIMENTO</text>
                    <text x="32" y="235" fill="#fff" font-size="13" font-weight="700" font-family="sans-serif">1 Jun · 360€ pendentes</text>
                </svg>
                <div class="scene-label">
                    <strong>Controlo financeiro</strong>
                    <span>Mensalidades, pagamentos e relatórios</span>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- FEATURES -->
<section class="features-section">
    <span class="eyebrow">Funcionalidades</span>
    <h2 class="sec-title">Tudo o que o teu clube precisa</h2>
    <p class="sec-sub">Uma plataforma completa para modernizar a gestão, do atleta ao relatório financeiro.</p>
    <div class="grid-6">
        <div class="feat">
            <span class="feat-ico">👤</span>
            <h3>Gestão de atletas</h3>
            <p>Fichas completas, histórico desportivo, documentos e controlo de inscrições num só lugar.</p>
        </div>
        <div class="feat">
            <span class="feat-ico">💶</span>
            <h3>Controlo financeiro</h3>
            <p>Mensalidades, pagamentos em atraso e relatórios detalhados em tempo real.</p>
        </div>
        <div class="feat">
            <span class="feat-ico">📅</span>
            <h3>Calendário de jogos</h3>
            <p>Treinos, jogos e eventos partilhados com toda a equipa num clique.</p>
        </div>
        <div class="feat">
            <span class="feat-ico">💬</span>
            <h3>Comunicação</h3>
            <p>Convocatórias, notificações e mensagens para atletas, pais e treinadores.</p>
        </div>
        <div class="feat">
            <span class="feat-ico">📊</span>
            <h3>Relatórios e stats</h3>
            <p>Dashboards com métricas em tempo real sobre desempenho e crescimento do clube.</p>
        </div>
        <div class="feat">
            <span class="feat-ico">🏟️</span>
            <h3>Multi-escalão</h3>
            <p>Seniores, sub-23, formação e futebol feminino — tudo na mesma conta.</p>
        </div>
    </div>
</section>

<!-- CTA BOTTOM -->
<div class="cta-strip">
    <h2>Pronto para digitalizar o teu clube?</h2>
    <p>Experimenta grátis durante 30 dias. Sem cartão de crédito. Sem compromisso.</p>
    <a href="registro.php" class="btn-white-cta">Criar clube grátis</a>
</div>

<footer>
    <span>© 2025 SportGes · Gestão Desportiva</span>
    <span><a href="#">Privacidade</a> · <a href="#">Termos</a> · <a href="#">Contacto</a></span>
</footer>

</body>
</html>