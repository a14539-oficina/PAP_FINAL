<?php
session_start();

require_once(__DIR__ . '/config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    $_SESSION['error'] = "Preencha todos os campos.";
    header('Location: login.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, email, password, role, club_id, nome FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "Utilizador não encontrado.";
    header('Location: login.php');
    exit;
}

if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Palavra-passe incorreta.";
    header('Location: login.php');
    exit;
}

$superAdmins = [
    'a14539@oficina.pt',
    'josemendes@oficina.pt'
];

if (in_array(strtolower($user['email']), array_map('strtolower', $superAdmins))) {
    $_SESSION['user_role'] = '0';
    $_SESSION['club_id']   = null;
} else {
    $_SESSION['user_role'] = $user['role'] ?? '4';
    $_SESSION['club_id']   = $user['club_id'] ?? 0;
}

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name']  = $user['nome'] ?? '';
$_SESSION['logged_in']  = true;

header('Location: dashboard.php');
exit;
?>