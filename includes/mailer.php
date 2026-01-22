<?php
// Mailer using PHPMailer
error_log('Loading mailer.php');

require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use setasign\Fpdi\Tcpdf\Fpdi;

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
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SMTPS for port 465
        $mail->Port = $smtpConfig['port'];

        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            } else {
                error_log("Attachment not found: $attachment");
            }
        }

        $mail->send();
        error_log("Email sent successfully to $to with subject: $subject");
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage() . ' | Host: ' . $smtpConfig['host'] . ' | Port: ' . $smtpConfig['port']);
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
    include_once __DIR__ . '/helpers.php';
    $candidates = getCandidates();
    if (!isset($candidates[$candidateId])) {
        error_log("Candidate $candidateId not found for email $templateName");
        return false;
    }
    $candidate = $candidates[$candidateId];
    $replacements = [
        'name' => $candidate['name'],
        'position' => $candidate['position'],
    ] + $extraReplacements;

    $subject = ucfirst($templateName) . ' for ' . $candidate['name'];
    $body = renderTemplate($templateName, $replacements);
    
    error_log("Attempting to send $templateName email to {$candidate['email']} with subject: $subject");
    
    $success = sendMail($candidate['email'], $subject, $body, $attachments);

    if ($success) {
        logRecruitmentAction($candidateId, $templateName, $_SESSION['user_id'] ?? 'system');
        error_log("$templateName email sent successfully to {$candidate['email']}");
    } else {
        error_log("$templateName email failed to send to {$candidate['email']}");
    }

    return $success;
}

function generateConfirmationPDF(array $candidate): ?string {
    error_log('generateConfirmationPDF function called for candidate: ' . $candidate['name']);
    
    $templatePath = __DIR__ . '/../pdf_templates/confirmation_template.pdf';
    if (!file_exists($templatePath)) {
        error_log('Confirmation PDF template not found at ' . $templatePath);
        return null;
    }

    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $tempDir .= DIRECTORY_SEPARATOR . 'pdf_' . uniqid('', true);
    if (!mkdir($tempDir, 0700, true)) {
        error_log('Failed to create temp dir for PDF: ' . $tempDir);
        return null;
    }

    $outputPdf = $tempDir . DIRECTORY_SEPARATOR . 'confirmation_' . time() . '.pdf';

    try {
        error_log('Starting PDF generation for candidate: ' . $candidate['name']);
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pageCount = $pdf->setSourceFile($templatePath);
        error_log("PDF template has $pageCount pages");

        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $pdf->importPage($p);
            $pdf->AddPage();
            $pdf->useTemplate($tplId, 0, 0, 210);

            if ($p === 1) {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(30, 180);
                $pdf->Cell(0, 6, 'Name: ' . $candidate['name'], 0, 1);
                $pdf->SetXY(30, 200);
                $pdf->Cell(0, 6, 'Position: ' . $candidate['position'], 0, 1);
                $pdf->SetXY(30, 220);
                $pdf->Cell(0, 6, 'Date: ' . date('d/m/Y'), 0, 1);
            }
        }

        $pdf->Output($outputPdf, 'F');
        error_log('PDF generated successfully with FPDI at: ' . $outputPdf);
        return $outputPdf;

    } catch (Throwable $e) {
        error_log('FPDI failed: ' . $e->getMessage() . ' - trying Imagick fallback');
        
        try {
            if (!extension_loaded('imagick')) {
                throw new RuntimeException('Imagick extension not available for fallback.');
            }

            $imagick = new Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($templatePath);

            $pngFiles = [];
            foreach ($imagick as $idx => $page) {
                $page->setImageFormat('png');
                $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $page->setBackgroundColor('white');

                $pngPath = $tempDir . DIRECTORY_SEPARATOR . sprintf('page_%02d.png', $idx + 1);
                $page->writeImage($pngPath);
                $pngFiles[] = $pngPath;
            }
            $imagick->clear();
            $imagick->destroy();

            $pdf2 = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf2->setPrintHeader(false);
            $pdf2->setPrintFooter(false);
            $pageWidth = 210;
            $pageHeight = 297;
            
            $pdf2->SetMargins(0, 0, 0);
            $pdf2->SetAutoPageBreak(false, 0);
            $pdf2->setImageScale(1.0);

            foreach ($pngFiles as $index => $png) {
                $pdf2->AddPage();
                $pdf2->Image($png, 0, 0, $pageWidth, $pageHeight, 'PNG', '', '', false, 300, '', false, false, 0);
                if ($index === 0) {
                    $pdf2->SetFont('helvetica', 'B', 12);
                    $pdf2->SetTextColor(0, 0, 0);
                    $pdf2->SetXY(28, 70);
                    $pdf2->Cell(0, 6, 'Name: ' . $candidate['name'], 0, 1);
                    $pdf2->SetXY(28, 80);
                    $pdf2->Cell(0, 6, 'Position: ' . $candidate['position'], 0, 1);
                    $pdf2->SetXY(28, 90);
                    $pdf2->Cell(0, 6, 'Date: ' . date('d/m/Y'), 0, 1);
                }
            }

            $pdf2->Output($outputPdf, 'F');

            foreach ($pngFiles as $f) if (file_exists($f)) @unlink($f);

            error_log('PDF generated successfully with Imagick fallback at: ' . $outputPdf);
            return $outputPdf;

        } catch (Throwable $e2) {
            error_log('Imagick fallback failed: ' . $e2->getMessage());
            @unlink($outputPdf);
            return null;
        }
    }
}
?>