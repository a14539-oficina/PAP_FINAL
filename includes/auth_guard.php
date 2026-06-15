<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db.php');

// =============================================================
// DEFINIÇÃO DE PAPÉIS
// =============================================================
define('ROLE_SUPERADMIN', '0');   // SuperAdmin (Nuno / RI)
define('ROLE_ADMINCLUBE', '1');   // Admin de Clube
define('ROLE_DIRETOR',    '2');
define('ROLE_TREINADOR',  '3');
define('ROLE_JOGADOR',    '4');

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$user_role  = $_SESSION['user_role'] ?? '';
$user_club  = (int)($_SESSION['club_id'] ?? 0);

// =============================================================
// BLOQUEAR SE NÃO ESTIVER LOGADO
// =============================================================
if ($user_id <= 0 || !$user_role) {
    session_destroy();
    header('Location: /SportGes/login.php');
    exit;
}

// =============================================================
// SE NÃO FOR SUPERADMIN NEM O USER ID 7, TEM DE TER CLUBE
// =============================================================
if ($user_role !== ROLE_SUPERADMIN && $user_id != 7 && $user_club <= 0) {
    die('⛔ Acesso bloqueado: o seu utilizador não tem clube atribuído.');
}

// =============================================================
// FUNÇÕES DE ACESSO
// =============================================================
function isSuperAdmin(): bool {
    return $_SESSION['user_role'] === ROLE_SUPERADMIN;
}

function isClubAdmin(): bool {
    return $_SESSION['user_role'] === ROLE_ADMINCLUBE;
}

function currentClubId(): int {
    return (int)($_SESSION['club_id'] ?? 0);
}