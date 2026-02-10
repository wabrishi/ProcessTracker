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
                error_log("✓ Attachment added: $attachment (size: " . filesize($attachment) . " bytes)");
            } else {
                error_log("✗ Attachment not found: $attachment");
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
    
    // First, handle double-brace placeholders (legacy format) - convert to single brace
    $content = preg_replace('/\{\{([A-Z_]+)\}\}/i', '{$1}', $content);
    
    // Now replace all placeholders
    foreach ($replacements as $key => $value) {
        $content = str_replace('{' . strtoupper($key) . '}', $value, $content);
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

function generateConfirmationPDF(array $candidate, string $candidateId = 'unknown'): ?string {
    error_log('generateConfirmationPDF function called for candidate: ' . $candidate['name'] . ' (ID: ' . $candidateId . ')');
    
    // Try to use decompressed version first, fallback to original
    $templatePath = __DIR__ . '/../pdf_templates/confirmation_uncompressed.pdf';
    if (!file_exists($templatePath)) {
        $templatePath = __DIR__ . '/../pdf_templates/confirmation_template.pdf';
    }
    
    if (!file_exists($templatePath)) {
        error_log('Confirmation PDF template not found');
        return null;
    }

    // Save PDFs permanently to uploads/letters/
    $lettersDir = __DIR__ . '/../uploads/letters';
    if (!is_dir($lettersDir)) {
        if (!mkdir($lettersDir, 0755, true)) {
            error_log('Failed to create letters dir for PDF: ' . $lettersDir);
            return null;
        }
    }

    $candidateFileId = $candidateId !== 'unknown' ? $candidateId : substr(md5($candidate['name']), 0, 8);
    $timestamp = time();
    $uniquePart = substr(md5(uniqid()), 0, 8);
    $outputPdf = $lettersDir . DIRECTORY_SEPARATOR . 'offer_' . $candidateFileId . '_' . $timestamp . '_' . $uniquePart . '.pdf';

    try {
        error_log('Starting PDF generation for candidate: ' . $candidate['name']);
        
        // Use setasign Fpdi to import PDF template
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Try to import the template
        error_log('Attempting to load template from: ' . $templatePath);
        $pageCount = $pdf->setSourceFile($templatePath);
        error_log("Template PDF has $pageCount pages");
        
        // Import first page as background
        $tplId = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplId, 0, 0, 210);
        
        // Overlay candidate information on top
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        
        // Add Name at position (30, 180)
        $pdf->SetXY(25, 70);
        $pdf->Cell(0, 6, 'Name: ' . $candidate['name'], 0, 1);
        
        // Add Position at position (30, 195)
        // $pdf->SetXY(30, 195);
        // $pdf->Cell(0, 6, 'Position: ' . $candidate['position'], 0, 1);
        
        // Add Date at position (30, 210)
        $pdf->SetXY(150, 70);
        $pdf->Cell(0, 6, 'Date: ' . date('d/m/Y'), 0, 1);
        
        // If template has more pages, import them too
        if ($pageCount > 1) {
            for ($p = 2; $p <= $pageCount; $p++) {
                $tplId = $pdf->importPage($p);
                $pdf->AddPage();
                $pdf->useTemplate($tplId);
            }
        }
        
        $pdf->Output($outputPdf, 'F');
        
        if (!file_exists($outputPdf)) {
            error_log('PDF file was not created at: ' . $outputPdf);
            return null;
        }
        
        error_log('PDF generated successfully using template at: ' . $outputPdf);
        error_log('PDF file size: ' . filesize($outputPdf) . ' bytes');
        return $outputPdf;

    } catch (Throwable $e) {
        error_log('PDF generation failed: ' . $e->getMessage());
        error_log('Exception trace: ' . $e->getTraceAsString());
        return null;
    }
}
?>