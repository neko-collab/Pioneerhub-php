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

function sendCourseRegistrationApproval($email, $name, $course_title) {
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
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Course Registration Approved";
        $mail->Body    = "
            <h2>Course Registration Approved</h2>
            <p>Dear $name,</p>
            <p>We're pleased to inform you that your registration for the course <b>$course_title</b> has been approved.</p>
            <p>You can now access all course materials and begin your learning journey.</p>
            <p>Thank you for choosing Pioneer Hub!</p>
            <p>Best regards,<br>Pioneer Hub Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendProjectCollaborationApproval($email, $name, $project_title) {
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
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Project Collaboration Request Approved";
        $mail->Body    = "
            <h2>Project Collaboration Approved</h2>
            <p>Dear $name,</p>
            <p>Your request to collaborate on the project <b>$project_title</b> has been approved!</p>
            <p>You can now work with the team and contribute to the project.</p>
            <p>Thank you for your interest in collaborating!</p>
            <p>Best regards,<br>Pioneer Hub Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendJobApplicationApproval($email, $name, $job_title, $company) {
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
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Job Application Status Update";
        $mail->Body    = "
            <h2>Job Application Accepted</h2>
            <p>Dear $name,</p>
            <p>We're pleased to inform you that your application for the position of <b>$job_title</b> at <b>$company</b> has been accepted.</p>
            <p>The hiring team will contact you shortly for the next steps in the process.</p>
            <p>Thank you for your interest in this opportunity!</p>
            <p>Best regards,<br>Pioneer Hub Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendInternshipApplicationApproval($email, $name, $internship_title, $company) {
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
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Internship Application Status Update";
        $mail->Body    = "
            <h2>Internship Application Accepted</h2>
            <p>Dear $name,</p>
            <p>We're pleased to inform you that your application for the <b>$internship_title</b> internship at <b>$company</b> has been accepted.</p>
            <p>The internship team will contact you shortly with more details about your onboarding.</p>
            <p>Congratulations on this opportunity!</p>
            <p>Best regards,<br>Pioneer Hub Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
