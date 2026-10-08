<?php

// Manual PHPMailer include - NO COMPOSER REQUIRED
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_notification($email, $message, $subject = "Clearance System Notification")
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'abdulrazakabdullaziz9@gmail.com';
        $mail->Password   = 'gzpv dfjw vsuz ndzo'; // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;

        // Recipients
        $mail->setFrom('abdulrazakabdullaziz9@gmail.com', 'Online Clearance System');
        $mail->addAddress($email);
        $mail->addReplyTo('abdulrazakabdullaziz9@gmail.com', 'Online Clearance System');

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>