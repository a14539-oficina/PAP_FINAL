<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require('config/db.php');
require('vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? AND ativo = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => true, 'message' => 'Se o email existir, receberá as instruções.']);
    exit;
}

$user = $res->fetch_assoc();

$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
$stmt2->bind_param("iss", $user['id'], $token, $expiry);
$stmt2->execute();

$reset_link = "http://localhost/sportges/reset_passe.php?token=" . $token;

$mail = new PHPMailer(true);

try {
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'sportges0@gmail.com';
$mail->Password   = 'yqzj diak qxfv plbu';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port       = 465;
$mail->CharSet    = 'UTF-8';
$mail->SMTPOptions = array(
    'ssl' => array(
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true
    )
);

    $mail->setFrom('sportges0@gmail.com', 'SportGes');
    $mail->addAddress($email, $user['full_name']);

    $mail->isHTML(true);
    $mail->Subject = 'Recuperação de palavra-passe · SportGes';
    $mail->Body    = '
    <!DOCTYPE html>
    <html lang="pt">
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
        <div style="max-width:520px;margin:40px auto;background:#fff;border-radius:20px;
                    box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="background:linear-gradient(135deg,#2563eb,#7c3aed);padding:40px;text-align:center;">
                <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:14px;
                            display:inline-flex;align-items:center;justify-content:center;
                            font-size:22px;font-weight:800;color:#fff;margin-bottom:16px;">SG</div>
                <h1 style="color:#fff;font-size:24px;font-weight:700;margin:0;">SportGes</h1>
            </div>
            <div style="padding:40px;">
                <h2 style="font-size:20px;font-weight:700;color:#111;margin:0 0 8px;">
                    Recuperar palavra-passe
                </h2>
                <p style="color:#666;font-size:15px;line-height:1.6;margin:0 0 24px;">
                    Olá <strong>' . htmlspecialchars($user['full_name']) . '</strong>,<br>
                    recebemos um pedido para recuperar a palavra-passe da sua conta.
                    Clique no botão abaixo para definir uma nova palavra-passe.
                </p>
                <div style="text-align:center;margin:32px 0;">
                    <a href="' . $reset_link . '" 
                       style="display:inline-block;background:#2563eb;color:#fff;
                              padding:16px 36px;border-radius:12px;font-size:16px;
                              font-weight:600;text-decoration:none;
                              box-shadow:0 4px 12px rgba(37,99,235,0.3);">
                        Redefinir palavra-passe
                    </a>
                </div>
                <p style="color:#999;font-size:13px;line-height:1.6;margin:0;">
                    Este link expira em <strong>1 hora</strong>.<br>
                    Se não pediu a recuperação, ignore este email — a sua conta está segura.
                </p>
                <hr style="border:none;border-top:1px solid #f0f0f0;margin:32px 0;">
                <p style="color:#bbb;font-size:12px;text-align:center;margin:0;">
                    © 2025 SportGes · Rational Innovation
                </p>
            </div>
        </div>
    </body>
    </html>';

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Se o email existir, receberá as instruções.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $mail->ErrorInfo]);
}