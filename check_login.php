<?php
session_start();

// Caminho correto para o ficheiro da base de dados
require_once(__DIR__ . '/../config/db.php'); // ajusta o caminho se o teu config estiver noutro local

// Mostrar erros (para debug)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===================================================
// 1️⃣ Verificar se o formulário foi submetido
// ===================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// ===================================================
// 2️⃣ Receber dados do formulário
// ===================================================
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    $_SESSION['error'] = "⚠️ Preencha todos os campos.";
    header('Location: login.php');
    exit;
}

// ===================================================
// 3️⃣ Buscar utilizador na base de dados
// ===================================================
$stmt = $conn->prepare("SELECT id, email, password, role, club_id, nome FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "❌ Utilizador não encontrado.";
    header('Location: login.php');
    exit;
}

// ===================================================
// 4️⃣ Verificar password
// ===================================================
if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "🔒 Palavra-passe incorreta.";
    header('Location: login.php');
    exit;
}

// ===================================================
// 5️⃣ Identificar SuperAdmins
// ===================================================
$superAdmins = [
    'nuno.teixeira@rationalinnovation.pt',
    'lopes@gmail.com'
];

if (in_array(strtolower($user['email']), array_map('strtolower', $superAdmins))) {
    // ✅ SuperAdmin — acesso total
    $_SESSION['user_role'] = '0';
    $_SESSION['club_id'] = null;
} else {
    // 🔹 Utilizador normal
    $_SESSION['user_role'] = $user['role'] ?? '4';
    $_SESSION['club_id'] = $user['club_id'] ?? 0;
}

// ===================================================
// 6️⃣ Guardar sessão
// ===================================================
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['nome'] ?? '';
$_SESSION['logged_in'] = true;

// ===================================================
// 7️⃣ Redirecionar para o dashboard
// ===================================================
header('Location: ../dashboard.php');
exit;
?>
