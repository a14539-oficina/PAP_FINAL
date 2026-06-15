<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportGes - Sidebar Menu</title>
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
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 260px;
            background: #0f172a;
            padding: 24px 0;
            z-index: 100;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #1e293b;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 10px;
        }

        .logo {
            padding: 0 24px;
            margin-bottom: 40px;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .logo p {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .menu-section {
            margin-bottom: 32px;
        }

        .menu-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 1px;
            padding: 0 24px;
            margin-bottom: 12px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin: 4px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 15px;
            font-weight: 500;
            position: relative;
        }

        .nav-link:hover {
            background: #1e293b;
            color: #ffffff;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: #3b82f6;
            color: #ffffff;
        }

        .nav-link i {
            font-size: 22px;
            margin-right: 12px;
            min-width: 22px;
        }

        .badge {
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: auto;
        }

        .divider {
            height: 1px;
            background: #1e293b;
            margin: 24px 16px;
        }

        /* User Profile */
        .user-profile {
            margin: 24px 12px 12px;
            padding: 16px;
            background: #1e293b;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-profile:hover {
            background: #334155;
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 12px;
            color: #64748b;
            display: block;
        }

        /* Demo Content Area */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        .demo-card {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .demo-card h1 {
            font-size: 32px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .demo-card p {
            color: #64748b;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h1>SportGes</h1>
            <p>Pro Manager</p>
        </div>

        <!-- Menu Principal -->
        <div class="menu-section">
            <div class="menu-title">Menu Principal</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link active">
                        <i class='bx bxs-dashboard'></i>
                        <span>Página Inicial</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Gestão -->
        <div class="menu-section">
            <div class="menu-title">Gestão</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../modules/clubes/listar.php" class="nav-link">
                        <i class='bx bx-building'></i>
                        <span>Clubes</span>
                        <span class="badge">3</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../../modules/jogadores/listar.php" class="nav-link">
                        <i class='bx bx-run'></i>
                        <span>Jogadores</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../../modules/despesas/listar.php" class="nav-link">
                        <i class='bx bx-receipt'></i>
                        <span>Despesas do Clube</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../../modules/equipas/listar.php" class="nav-link">
                        <i class='bx bx-group'></i>
                        <span>Equipas e Escalões</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../../modules/jogos/listar.php" class="nav-link">
                        <i class='bx bx-football'></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../../modules/epoca/listar.php" class="nav-link">
                        <i class='bx bx-football'></i>
                        <span>Época</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="divider"></div>

        <!-- Conta -->
        <div class="menu-section">
            <div class="menu-title">Conta</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/perfil.php" class="nav-link">
                        <i class='bx bx-user'></i>
                        <span>Perfil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/definicoes.php" class="nav-link">
                        <i class='bx bx-cog'></i>
                        <span>Definições</span>
                    </a>
                </li>
            </ul>
        </div>

       

    <!-- Demo Main Content -->
    <div class="main-content">
        <div class="demo-card">
            <h1>Bem-vindo ao SportGes! 👋</h1>
            <p>Este é o seu menu lateral modernizado. Navegue pelos diferentes módulos usando o menu à esquerda.</p>
        </div>
    </div>

    <script>
        // Sistema de menu ativo baseado na página atual
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.nav-link');
            
            // Remove active de todos
            menuItems.forEach(item => item.classList.remove('active'));
            
            // Adiciona active ao item correspondente
            menuItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && currentPath.includes(href)) {
                    item.classList.add('active');
                }
            });
            
            // Se nenhum item estiver ativo, ativa o dashboard por padrão
            if (!document.querySelector('.nav-link.active')) {
                const homeLink = document.querySelector('[href="../../index.php"]');
                if (homeLink) {
                    homeLink.classList.add('active');
                }
            }
        });

        // Click nos itens do menu
        document.querySelectorAll('.nav-link').forEach(item => {
            item.addEventListener('click', function(e) {
                // Remove active de todos e adiciona ao clicado
                document.querySelectorAll('.nav-link').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>

</body>
</html>