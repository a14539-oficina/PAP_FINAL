# SportGes — Sistema de Gestão Desportiva

> **PAP · Aluno n.º 14539 · Gestão e Programação de Sistemas Informáticos**  
> Professor Orientador: Luís Mendes | Alojamento: InfinityFree (`sql300.infinityfree.com`)

---

## Descrição

O **SportGes** é uma plataforma web completa para a gestão de clubes e equipas desportivas. Permite gerir jogadores, staff, jogos, treinos, épocas desportivas e finanças, com um sistema de permissões granular por perfil de utilizador.

---

## Tecnologias Utilizadas

| Tecnologia | Versão | Função |
|---|---|---|
| PHP | 7.4+ | Lógica de servidor |
| MySQL | 5.7+ | Base de dados relacional |
| HTML5 / CSS3 / JavaScript | ES6+ | Front-end |
| PHPMailer | 7.x | Envio de e-mails (via Composer) |
| Bootstrap Icons / Boxicons | — | Ícones de interface |

---

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor Apache/Nginx com suporte a PHP
- Extensões PHP: `mysqli`, `mbstring`, `openssl`, `fileinfo`
- Composer (para instalar o PHPMailer)

---

## Instalação

### 1. Upload dos ficheiros
Carregue todos os ficheiros para a pasta `htdocs` (ou `www`) do servidor web.

### 2. Base de dados
Importe o ficheiro SQL disponível em `config/base de dados/` para o seu servidor MySQL:

```bash
mysql -u utilizador -p nome_base_dados < sportges.sql
```

### 3. Configurar ligação à base de dados
Edite o ficheiro `config/db.php`:

```php
$host   = "sql300.infinityfree.com";
$user   = "if0_42155522";
$pass   = "SUA_SENHA_AQUI";
$dbname = "if0_42155522_sportges";
$port   = 3306;
```

### 4. Instalar dependências
Na raiz do projeto, execute:

```bash
composer install
```

### 5. Permissões de pastas
Certifique-se de que as seguintes pastas têm permissão de escrita:

```bash
chmod 755 logos/
chmod 755 modules/clubes/uploads/
chmod 755 modules/equipas/logos/
```

### 6. Configurar e-mail *(opcional)*
Edite `recuperar_senha.php` e `contacto.php` com as suas credenciais SMTP.

### 7. Acesso ao sistema
Abra o browser e aceda ao endereço do seu servidor. Registe o primeiro utilizador como **SuperAdmin**.

---

## Estrutura do Projeto

```
SportGes/
├── config/                        → Configuração da base de dados e controlo de acesso
│   ├── db.php                     → Ligação MySQL
│   ├── checkClubAccess.php        → Verificação de acesso por clube
│   └── base de dados/             → Ficheiro(s) SQL de instalação
├── includes/                      → Componentes reutilizáveis
│   ├── auth_guard.php             → Proteção de sessão e definição de roles
│   ├── role_helper.php            → Funções auxiliares de permissões
│   ├── menu.php / sidebar.php     → Navegação
│   └── topbar.php / footer.php    → Layout global
├── modules/                       → Módulos funcionais
│   ├── clubes/                    → CRUD de clubes com logótipo
│   ├── equipas/                   → Equipas por clube/época, transferências
│   ├── jogadores/                 → Fichas, fotos, estatísticas, playerCentral
│   ├── jogos/                     → Resultados, golos, assistências, cartões
│   ├── treinos/                   → Agendamento e registo de presenças
│   ├── epoca/                     → Criação e gestão de épocas desportivas
│   ├── staff/                     → Registo de treinadores e auxiliares
│   ├── estatisticas/              → Rankings e estatísticas individuais/coletivas
│   ├── despesas/                  → Despesas e relatórios financeiros
│   ├── lucro/                     → Receitas, mensalidades e relatórios
│   └── livescore/                 → Acompanhamento de jogos em tempo real
├── public/
│   └── css/                       → Folhas de estilo (style.css, dashboard.css)
├── vendor/                        → PHPMailer (gerido pelo Composer)
├── logos/                         → Fotos de jogadores
├── dashboard.php                  → Painel principal
├── login.php                      → Autenticação
├── registro.php                   → Registo de utilizadores
├── recuperar_senha.php            → Recuperação de palavra-passe
├── meu_perfil.php                 → Perfil do utilizador
├── pagina_inicio.php              → Página pública
├── contacto.php                   → Formulário de contacto
└── composer.json                  → Dependências PHP
```

---

## Perfis de Utilizador

| Perfil | Role | Permissões |
|---|:---:|---|
| **SuperAdmin** | `0` | Acesso total: gere todos os clubes, utilizadores, épocas e configurações globais. Visualiza totais financeiros de todos os clubes. |
| **Administrador** | `1` | Gere o seu clube: equipas, jogadores, staff, finanças e jogos. Acesso total aos módulos do clube associado. |
| **Diretor** | `2` | Supervisiona o clube: consulta relatórios financeiros, estatísticas e toda a atividade desportiva. Sem permissões de edição. |
| **Treinador** | `3` | Visualiza a equipa associada, regista treinos e consulta estatísticas dos jogadores. |
| **Jogador** | `4` | Acede ao seu perfil, consulta estatísticas pessoais, jogos e sessões de treino da sua equipa. |

---

## Módulos Disponíveis

- **Clubes** — Criação e gestão de clubes com logótipo
- **Equipas** — Equipas por clube e época, com logótipo e transferências de jogadores
- **Jogadores** — Ficha completa, foto, posição, estatísticas e página `playerCentral`
- **Jogos** — Resultados, golos, assistências e cartões por jogo
- **Treinos** — Agendamento, registo de presenças e consulta por equipa
- **Época Desportiva** — Criação, edição e gestão de épocas (temporadas)
- **Staff** — Registo e gestão de treinadores e auxiliares
- **Estatísticas** — Rankings individuais e coletivos por época e por jogo
- **Despesas** — Registo de despesas, categorias e geração automática de relatórios (cron)
- **Receitas / Mensalidades** — Receitas, mensalidades de jogadores e relatórios financeiros
- **LiveScore** — Registo de golos em tempo real e finalização de jogos

---

## Notas de Segurança

- Palavras-passe encriptadas com `password_hash()` / `password_verify()`
- Proteção CSRF em formulários sensíveis
- Validação de sessão em todas as páginas privadas via `auth_guard.php`
- Controlo de acesso por role em todas as operações (SuperAdmin, Admin, Diretor, Treinador, Jogador)
- Sanitização de inputs com `htmlspecialchars()` e prepared statements (`mysqli`)
- Charset `utf8mb4` na ligação à base de dados

---

## Contacto e Suporte

Para questões sobre o projeto, utilize o formulário de contacto disponível em `contacto.php`.

---

*SportGes © 2026 — PAP Aluno 14539 | Professor Orientador: Luís Mendes*
