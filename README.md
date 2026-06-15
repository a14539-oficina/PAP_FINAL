# SportGes — Sistema de Gestão Desportiva

PAP · Aluno n.º 14539 · Gestão e Programação de Sistemas Informáticos**
Professor Orientador: Luís Mendes | Alojamento: InfinityFree (`sql300.infinityfree.com`)

---

## Descrição

O **SportGes** é uma plataforma web completa para a gestão de clubes e equipas desportivas. Permite gerir jogadores, staff, jogos, treinos, épocas desportivas e finanças, com um sistema de permissões por perfil de utilizador (Admin Principal, Administrador de Clube, Treinador).

---

## Tecnologias Utilizadas

| Tecnologia | Versão | Função |
|---|---|---|
| PHP | 7.4+ | Lógica de servidor |
| MySQL | 5.7+ | Base de dados relacional |
| HTML5 / CSS3 / JavaScript | ES6+ | Front-end |
| PHPMailer | 6.x | Envio de e-mails (via Composer) |
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
$pass   = "9ZWjHqmsS880KkX";
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
Abra o browser e aceda ao endereço do seu servidor. Registe o primeiro utilizador como **Administrador Principal**.

---

## Estrutura do Projeto

```
SportGes/
├── config/                  → Configuração da base de dados
├── includes/                → Componentes reutilizáveis (menu, header, footer)
├── modules/                 → Módulos funcionais
│   ├── clubes/
│   ├── equipas/
│   ├── jogadores/
│   ├── jogos/
│   ├── treinos/
│   ├── epoca/
│   ├── staff/
│   ├── estatisticas/
│   ├── despesas/
│   ├── lucro/
│   └── livescore/
├── vendor/                  → PHPMailer (Composer)
├── logos/                   → Fotos de jogadores
├── dashboard.php            → Painel principal
├── login.php                → Autenticação
├── pagina_inicio.php        → Página pública
└── README.md                → Este ficheiro
```

---

## Perfis de Utilizador

| Perfil | Role | Descrição |
|---|:---:|---|
| SuperAdmin | `0` | Acesso total ao sistema: gere todos os clubes, utilizadores, épocas e configurações globais. |
| Administrador | `1` | Gere o seu clube: equipas, jogadores, staff, finanças e jogos do clube associado. |
| Diretor | `2` | Supervisiona o clube, consulta relatórios financeiros e estatísticas, sem permissões de edição. |
| Treinador | `3` | Visualiza a equipa associada, regista treinos e consulta estatísticas dos jogadores. |
| Jogador | `4` | Acede ao seu perfil, consulta estatísticas pessoais, jogos e sessões de treino da sua equipa. |

---

## Módulos Disponíveis

- **Clubes** — Criação e gestão de clubes com logótipo
- **Equipas** — Equipas por clube e época, com logótipo
- **Jogadores** — Ficha completa, foto, estatísticas e transferências
- **Jogos** — Resultados, golos, assistências e cartões por jogo
- **Treinos** — Agendamento e registo de presenças
- **Época Desportiva** — Criação e gestão de épocas
- **Staff** — Registo de treinadores e auxiliares
- **Estatísticas** — Rankings e estatísticas individuais/coletivas
- **Finanças** — Receitas, despesas e relatórios financeiros
- **LiveScore** — Acompanhamento de jogos em tempo real

---

## Notas de Segurança

- Palavras-passe encriptadas com `password_hash()` / `password_verify()`
- Proteção CSRF em formulários sensíveis
- Validação de sessão em todas as páginas privadas (`auth_guard.php`)
- Controlo de acesso por papel (role) em todas as operações

---

## Contacto e Suporte

Para questões sobre o projeto, utilize o formulário de contacto disponível em `contacto.php`.

---

*SportGes © 2026 — PAP Aluno 14539 | Professor Orientador: Luís Mendes*
