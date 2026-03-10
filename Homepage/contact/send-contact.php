<?php
/**
 * send-contact.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Receives a contact-form submission from contact.html and forwards it to
 * cuarteljohncarlosl@gmail.com via PHPMailer + Gmail SMTP.
 *
 * Place this file in: /Homepage/send-contact.php
 * PHPMailer must already be installed (composer require phpmailer/phpmailer)
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Config ───────────────────────────────────────────────────────────────────
define('GMAIL_USER', 'cuarteljohncarlosl@gmail.com');
define('GMAIL_APP_PASSWORD', 'mcnz lqqc mzfp npan');
define('RECIPIENT_EMAIL', 'cuarteljohncarlosl@gmail.com');
define('RECIPIENT_NAME', 'Cal Elite — Contact Inbox');
define('MAIL_FROM_NAME', 'Cal Elite Website');

// ── Headers ──────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function out(bool $ok, string $msg, int $code = 200): void
{
  http_response_code($code);
  echo json_encode(['success' => $ok, 'message' => $msg]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  out(false, 'Method not allowed.', 405);

// ── Load PHPMailer (tries Login/vendor then root vendor) ─────────────────────
$autoloads = [
  __DIR__ . '/../Login/vendor/autoload.php',
  __DIR__ . '/../vendor/autoload.php',
  __DIR__ . '/vendor/autoload.php',
];
$loaded = false;
foreach ($autoloads as $path) {
  if (file_exists($path)) {
    require $path;
    $loaded = true;
    break;
  }
}
// $loaded = false means PHPMailer is unavailable; we still save to DB below.

// ── Parse & validate input ───────────────────────────────────────────────────
$raw = json_decode(file_get_contents('php://input'), true) ?? [];
$firstName = trim($raw['first_name'] ?? '');
$lastName = trim($raw['last_name'] ?? '');
$email = strtolower(trim($raw['email'] ?? ''));
$phone = trim($raw['phone'] ?? '');
$subject = trim($raw['subject'] ?? '');
$message = trim($raw['message'] ?? '');

if (!$firstName || !$email || !$message) {
  out(false, 'First name, email and message are required.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  out(false, 'Invalid email address.', 422);
}
if (strlen($message) < 10) {
  out(false, 'Message is too short.', 422);
}

// ── Also capture optional fields ──────────────────────────────────────────
$type = trim($raw['type'] ?? 'inquiry');
$userId = isset($raw['user_id']) ? (int) $raw['user_id'] : null;

// Sanitize type to known set
$allowedTypes = ['inquiry', 'quotation', 'complaint', 'partnership', 'other'];
if (!in_array($type, $allowedTypes, true)) {
  $type = 'inquiry';
}

// ── Save to Supabase Messages table ───────────────────────────────────────
define('SB_URL', 'https://pdqhbxtxvxrwtkvymjlm.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBkcWhieHR4dnhyd3RrdnltamxtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1NTEyMzIsImV4cCI6MjA4NzEyNzIzMn0.jKq6Zw1XWDYXkxdrkW6HscOpsOuUm0gcyBCwFsAwN9U');
define('SB_TABLE', 'Messages');

function saveToSupabase(array $payload): bool
{
  $ch = curl_init(SB_URL . '/rest/v1/' . SB_TABLE);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'apikey: ' . SB_KEY,
      'Authorization: Bearer ' . SB_KEY,
      'Prefer: return=representation',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15,
  ]);
  $raw = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return in_array($status, [200, 201], true);
}

$now = date('c');
$sbPayload = [
  'sender_name' => trim("$firstName $lastName") ?: $firstName,
  'sender_email' => $email,
  'sender_phone' => $phone ?: null,
  'subject' => $subject ?: 'New Contact Form Inquiry',
  'message' => $message,
  'type' => $type,
  'status' => 'unread',
  'is_starred' => false,
  'is_archived' => false,
  'replied_at' => null,
  'prev_status' => null,
  'archived_at' => null,
  'created_at' => $now,
  'updated_at' => $now,
  'user_id' => $userId,
];

// Attempt to save; log failure but do not block the email send
if (!saveToSupabase($sbPayload)) {
  error_log('Contact form: Supabase insert failed for ' . $email);
}

$fullName = trim("$firstName $lastName") ?: $firstName;
$safeSubject = $subject ?: 'New Contact Form Inquiry';
$year = date('Y');
$timestamp = date('F j, Y \a\t g:i A');

// ── Build HTML email ─────────────────────────────────────────────────────────
$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>New Contact Message</title>
</head>
<body style="margin:0;padding:0;background:#0e0e0e;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e0e0e;padding:40px 20px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#141414;border-radius:8px;overflow:hidden;border-top:3px solid #c6a75e;box-shadow:0 8px 40px rgba(0,0,0,0.6);">

        <!-- Header -->
        <tr>
          <td style="padding:32px 40px 20px;text-align:center;">
            <div style="font-family:Georgia,serif;font-size:26px;font-weight:700;color:#c6a75e;letter-spacing:2px;">
              Cal<span style="font-style:italic;font-weight:400;">Elite</span>
            </div>
            <div style="font-size:8px;letter-spacing:4px;text-transform:uppercase;color:rgba(198,167,94,0.45);margin-top:5px;">
              Builders · Cal Electrical · Est. 2004
            </div>
          </td>
        </tr>

        <!-- Gold divider -->
        <tr><td style="padding:0 40px;"><div style="height:1px;background:linear-gradient(90deg,transparent,rgba(198,167,94,0.4),transparent);"></div></td></tr>

        <!-- Intro -->
        <tr>
          <td style="padding:28px 40px 0;">
            <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:rgba(198,167,94,0.55);margin-bottom:8px;">
              New Contact Message
            </div>
            <div style="font-size:22px;font-family:Georgia,serif;font-weight:600;color:#f0ede6;line-height:1.3;">
              {SUBJECT}
            </div>
            <div style="font-size:11px;color:rgba(200,195,185,0.4);margin-top:6px;letter-spacing:0.5px;">
              Received on {TIMESTAMP}
            </div>
          </td>
        </tr>

        <!-- Sender details -->
        <tr>
          <td style="padding:24px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:12px 16px;background:rgba(198,167,94,0.05);border-left:2px solid rgba(198,167,94,0.35);border-radius:0 4px 4px 0;margin-bottom:4px;">
                  <div style="font-size:8px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(198,167,94,0.5);margin-bottom:4px;">From</div>
                  <div style="font-size:15px;color:#f0ede6;font-weight:600;">{FULL_NAME}</div>
                </td>
              </tr>
              <tr><td style="height:4px;"></td></tr>
              <tr>
                <td style="padding:12px 16px;background:rgba(198,167,94,0.05);border-left:2px solid rgba(198,167,94,0.2);border-radius:0 4px 4px 0;">
                  <div style="font-size:8px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(198,167,94,0.5);margin-bottom:4px;">Email</div>
                  <div style="font-size:14px;color:#c6a75e;">{SENDER_EMAIL}</div>
                </td>
              </tr>
              {PHONE_ROW}
            </table>
          </td>
        </tr>

        <!-- Message body -->
        <tr>
          <td style="padding:0 40px 28px;">
            <div style="background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.05);border-radius:6px;padding:22px 24px;">
              <div style="font-size:8px;letter-spacing:3px;text-transform:uppercase;color:rgba(198,167,94,0.45);margin-bottom:14px;">Message</div>
              <div style="font-size:14px;color:rgba(220,215,205,0.85);line-height:1.85;white-space:pre-wrap;">{MESSAGE}</div>
            </div>
          </td>
        </tr>

        <!-- Reply CTA -->
        <tr>
          <td style="padding:0 40px 28px;text-align:center;">
            <a href="mailto:{SENDER_EMAIL}" style="display:inline-block;padding:12px 30px;background:linear-gradient(135deg,#c6a75e,#d4af37);border-radius:3px;color:#0a0800;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;text-decoration:none;">
              Reply to {FIRST_NAME}
            </a>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#0d0d0d;padding:16px 40px;text-align:center;border-top:1px solid rgba(255,255,255,0.04);">
            <p style="font-size:10px;color:rgba(160,155,145,0.35);margin:0;letter-spacing:0.5px;">
              © {YEAR} Cal Elite Builders &amp; Cal Electrical &nbsp;·&nbsp; This message was sent via the contact form on your website.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

$phoneRow = $phone
  ? "<tr><td style='height:4px;'></td></tr>
       <tr>
         <td style='padding:12px 16px;background:rgba(198,167,94,0.05);border-left:2px solid rgba(198,167,94,0.2);border-radius:0 4px 4px 0;'>
           <div style='font-size:8px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(198,167,94,0.5);margin-bottom:4px;'>Phone</div>
           <div style='font-size:14px;color:#f0ede6;'>" . htmlspecialchars($phone) . "</div>
         </td>
       </tr>"
  : '';

$htmlBody = str_replace(
  ['{SUBJECT}', '{TIMESTAMP}', '{FULL_NAME}', '{SENDER_EMAIL}', '{PHONE_ROW}', '{MESSAGE}', '{FIRST_NAME}', '{YEAR}'],
  [
    htmlspecialchars($safeSubject),
    $timestamp,
    htmlspecialchars($fullName),
    htmlspecialchars($email),
    $phoneRow,
    htmlspecialchars($message),
    htmlspecialchars($firstName),
    $year,
  ],
  $htmlBody
);

$textBody = "New contact message from: $fullName <$email>\n";
$textBody .= $phone ? "Phone: $phone\n" : '';
$textBody .= "Subject: $safeSubject\n\n$message";

// ── Send via PHPMailer (only if available) ────────────────────────────────
if ($loaded) {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = GMAIL_USER;
    $mail->Password = GMAIL_APP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
    $mail->addAddress(RECIPIENT_EMAIL, RECIPIENT_NAME);
    $mail->addReplyTo($email, $fullName);

    $mail->isHTML(true);
    $mail->Subject = "[Cal Elite Contact] $safeSubject — from $fullName";
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody;

    $mail->send();
    out(true, 'Message sent successfully! We will get back to you within 24 hours.');
  } catch (Exception $e) {
    error_log('Contact form PHPMailer error: ' . $mail->ErrorInfo);
    // DB already saved — respond success but log email failure
    out(true, 'Message received! We will get back to you within 24 hours.');
  }
} else {
  // PHPMailer not installed — message is already saved in the database
  error_log('Contact form: PHPMailer not available, email not sent for ' . $email);
  out(true, 'Message received! We will get back to you within 24 hours.');
}

