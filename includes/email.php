<?php
/**
 * Email Helper using PHPMailer with SMTP
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer SMTP
 *
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML body content
 * @param string $plainBody Plain text body (optional)
 * @return bool Success status
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Disable SSL verification for local/dev (remove in production if issues)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

        $mail->send();
        error_log("Email sent successfully to: {$toEmail}");
        return true;

    } catch (Exception $e) {
        error_log("Email failed to {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail(string $toEmail, string $userName, string $resetLink): bool {
    $subject = "OAC - Password Reset Request";

    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #b76e79; margin: 0; font-family: Georgia, serif; }
            .content { color: #333; line-height: 1.6; }
            .button { display: inline-block; background: #b76e79; color: #fff !important; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center; }
            .warning { color: #e67e22; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>OAC App</h1>
            </div>
            <div class='content'>
                <p>Dear {$userName},</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; font-size: 13px; color: #666;'>{$resetLink}</p>
                <p class='warning'>⚠️ This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from OAC App. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";

    $plainBody = "Dear {$userName},\n\n";
    $plainBody .= "We received a request to reset your password.\n\n";
    $plainBody .= "Click the link below to reset your password:\n";
    $plainBody .= "{$resetLink}\n\n";
    $plainBody .= "This link will expire in 1 hour.\n\n";
    $plainBody .= "If you did not request a password reset, please ignore this email.\n\n";
    $plainBody .= "- OAC App";

    return sendEmail($toEmail, $userName, $subject, $htmlBody, $plainBody);
}

/**
 * Send birthday notification email
 */
function sendBirthdayEmail(string $toEmail, string $userName, string $birthdayPerson): bool {
    $subject = "🎂 Birthday Today - {$birthdayPerson}";

    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { color: #b76e79; margin: 0; }
            .cake { font-size: 48px; text-align: center; margin: 20px 0; }
            .content { color: #333; line-height: 1.6; text-align: center; }
            .name { font-size: 24px; color: #b76e79; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Birthday Reminder</h1>
            </div>
            <div class='cake'>🎂</div>
            <div class='content'>
                <p>Dear {$userName},</p>
                <p>Today is a special day!</p>
                <p class='name'>{$birthdayPerson}</p>
                <p>is celebrating their birthday today!</p>
                <p>Don't forget to send your wishes! 🎈</p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($toEmail, $userName, $subject, $htmlBody);
}
