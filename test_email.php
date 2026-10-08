<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Debug output ON
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'abdulrazakabdullaziz9@gmail.com';
    $mail->Password   = 'gzpv dfjw vsuz ndzo';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('abdulrazakabdullaziz9@gmail.com', 'Online Clearance System');
    $mail->addAddress('abdulrazakabdullaziz9@gmail.com'); // send to yourself for testing

    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body    = '<h1>It works!</h1><p>Email sending is working.</p>';

    $mail->send();
    echo "✅ <strong>SUCCESS!</strong> Email sent successfully!";
    
} catch (Exception $e) {
    echo "❌ <strong>FAILED!</strong><br>";
    echo "Error: " . $mail->ErrorInfo;
}