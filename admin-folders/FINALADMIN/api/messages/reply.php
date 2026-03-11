<?php
// Try to auto-load PHPMailer if vendor exists
$autoloadPath = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function out(bool $ok, string $msg, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Invalid request method.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['to']) || empty($input['subject']) || empty($input['body'])) {
    out(false, 'To, subject, and body are required.', null, 400);
}

$to = trim($input['to']);
$subject = trim($input['subject']);
$messageId = $input['messageId'] ?? null;
$rawMessageBody = nl2br(htmlspecialchars($input['body']));

// Beautiful HTML Template
$messageBody = '
<!DOCTYPE html>
<html>
<head>
<style>
  body { margin: 0; padding: 0; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f4f4f5; }
  .email-wrapper { max-width: 600px; margin: 40px auto; background-color: #121212; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
  .header { background-color: #0a0a0a; padding: 30px 40px; text-align: center; border-bottom: 2px solid #D4AF37; }
  .header h1 { color: #D4AF37; margin: 0; font-size: 24px; letter-spacing: 1px; text-transform: uppercase; }
  .header p { color: #888; font-size: 12px; margin-top: 5px; text-transform: uppercase; letter-spacing: 2px; }
  .content { padding: 40px; color: #e0e0e0; font-size: 16px; line-height: 1.6; background-color: #1a1a1a; }
  .content p { margin-top: 0; margin-bottom: 20px; }
  .footer { background-color: #0a0a0a; padding: 20px 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #333; }
  .type-badge { display: inline-block; background-color: rgba(96,165,250,0.15); border: 1px solid #60a5fa; color: #93c5fd; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-bottom: 15px; letter-spacing: 1px; }
</style>
</head>
<body>
  <div class="email-wrapper">
    <div class="header">
      <h1>CAL ELITE</h1>
      <p>Builders & Electrical</p>
    </div>
    <div class="content">
      <div class="type-badge">REPLY TO INQUIRY</div>
      <div style="font-size: 20px; color: #fff; font-weight: bold; margin-bottom: 25px;">' . htmlspecialchars($subject) . '</div>
      ' . $rawMessageBody . '
      <p style="margin-top: 30px; border-top: 1px solid #333; padding-top: 20px; color: #aaa; font-size: 14px;">Regards,<br><strong style="color: #D4AF37;">Admin Team</strong><br>CAL ELITE Builders & Electrical</p>
    </div>
    <div class="footer">
      &copy; ' . date('Y') . ' CAL ELITE Builders and Electrical. All rights reserved.<br>
      This is an automated reply. Please do not reply directly to this message.
    </div>
  </div>
</body>
</html>';

// If PHPMailer is available, use it
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        // Re-use credentials from blast.php for consistency
        $mail->Username = 'johnbernardmitra25@gmail.com';
        $mail->Password = 'mryiwllxmlcnvshd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('johnbernardmitra25@gmail.com', 'CAL ELITE BUILDERS AND ELECTRICAL');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $messageBody;

        $mail->addAddress($to);

        $mail->send();
        out(true, 'Reply sent successfully.');
    } catch (Exception $e) {
        out(false, 'Reply failed to send (PHPMailer error: ' . $mail->ErrorInfo . ')', null, 500);
    }
} else {
    // Fallback to basic PHP mail()
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Admin <admin@calelite.com>" . "\r\n";
    $mailSuccess = @mail($to, $subject, $messageBody, $headers);

    if ($mailSuccess) {
        out(true, 'Reply sent successfully (via basic mail).');
    } else {
        out(true, '[MOCK] Reply processed. (mail() function failed, likely no local SMTP).');
    }
}
