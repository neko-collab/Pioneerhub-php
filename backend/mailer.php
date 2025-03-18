<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

function sendEmailOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $dotenv = parse_ini_file("../.env");

        $mail->isSMTP();
        $mail->Host       = $dotenv["SMTP_HOST"];
        $mail->SMTPAuth   = true;
        $mail->Username   = $dotenv["SMTP_USER"];
        $mail->Password   = $dotenv["SMTP_PASS"];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $dotenv["SMTP_PORT"];

        $mail->setFrom($dotenv["SMTP_USER"], 'Pioneer Hub');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code";
        $mail->Body    = "Your OTP is <b>$otp</b>. It is valid for 15 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
