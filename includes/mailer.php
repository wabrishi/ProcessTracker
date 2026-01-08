<?php
// Mailer using PHPMailer

require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$smtpConfig = json_decode(file_get_contents(__DIR__ . '/../config/smtp.json'), true);

function sendMail(string $to, string $subject, string $htmlBody, array $attachments = []): bool {
    global $smtpConfig;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];

        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}

function renderTemplate(string $templateName, array $replacements): string {
    $templatePath = __DIR__ . '/../mail_templates/' . $templateName . '.html';
    if (!file_exists($templatePath)) {
        return 'Template not found';
    }
    $content = file_get_contents($templatePath);
    foreach ($replacements as $key => $value) {
        $content = str_replace('{{' . $key . '}}', $value, $content);
    }
    return $content;
}

function sendTemplatedMail(string $candidateId, string $templateName, array $extraReplacements = [], array $attachments = []): bool {
    include __DIR__ . '/helpers.php';
    $candidates = getCandidates();
    if (!isset($candidates[$candidateId])) {
        return false;
    }
    $candidate = $candidates[$candidateId];
    $replacements = [
        'name' => $candidate['name'],
        'position' => $candidate['position'],
    ] + $extraReplacements;

    $subject = ucfirst($templateName) . ' for ' . $candidate['name'];
    $body = renderTemplate($templateName, $replacements);

    $success = sendMail($candidate['email'], $subject, $body, $attachments);

    if ($success) {
        logRecruitmentAction($candidateId, $templateName, $_SESSION['user_id'] ?? 'system');
    }

    return $success;
}
?>