<?php
require_once 'vendor/autoload.php';
require_once 'includes/mailer.php';

$testEmail = 'test@example.com'; // Replace with your email
$result = sendMail($testEmail, 'Test Email', '<h1>Test</h1><p>This is a test email.</p>');

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send.";
}
?>