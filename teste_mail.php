<?php
require('vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $mail->addAddress('sportges0@gmail.com', 'Teste');
    $mail->isHTML(true);
    $mail->Subject = 'Teste SportGes';
    $mail->Body    = 'Este é um email de teste do SportGes.';

    $mail->send();
    echo 'Email enviado!';
} catch (Exception $e) {
    echo 'Erro: ' . $mail->ErrorInfo;
}