<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    /**
     * Kirim email menggunakan PHPMailer.
     */
    public static function send(string $to, string $subject, string $message): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0; // Disable debug output
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? '';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['MAIL_PORT'] ?? 587;

            // Recipients
            $mail->setFrom(
                $_ENV['MAIL_FROM'] ?? 'no-reply@hris.local',
                $_ENV['MAIL_FROM_NAME'] ?? 'HRIS System'
            );
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br($message);
            $mail->AltBody = strip_tags($message);

            $mail->send();
            return true;
        } catch (Exception) {
            error_log("MAIL ERROR: {$mail->ErrorInfo}");

            // Fallback ke log jika gagal (opsional)
            self::logFallback($to, $subject, $message);
            return false;
        }
    }

    /**
     * Template email OTP.
     */
    public static function sendOtp(string $to, string $otpCode): bool
    {
        $subject = "Your OTP Verification Code";
        $message = "Your verification code is: <b>$otpCode</b><br><br>";
        $message .= "This code will expire in 15 minutes. Please do not share this code with anyone.";

        return self::send($to, $subject, $message);
    }

    /**
     * Welcome email for new users with a magic link to set their password.
     */
    public static function sendWelcomeWithOtp(string $to, string $name, string $otpCode): bool
    {
        $appName = $_ENV['APP_NAME'] ?? 'HRIS System';
        $frontendUrl = rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:3000', '/');
        $link = $frontendUrl . '/set-password?email=' . urlencode($to) . '&token=' . urlencode($otpCode);

        $subject = "Welcome to $appName - Set Your Password";
        $message = "Hello <b>$name</b>,<br><br>";
        $message .= "Your account has been created in <b>$appName</b>.<br><br>";
        $message .= "Click the button below to set your password. This link is valid for <b>15 minutes</b>.<br><br>";
        $message .= "<a href='$link' style='display:inline-block;padding:12px 24px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;'>Set My Password</a><br><br>";
        $message .= "If the button does not work, copy and paste this link into your browser:<br>$link<br><br>";
        $message .= "Do not share this link with anyone.";

        return self::send($to, $subject, $message);
    }

    private static function logFallback(string $to, string $subject, string $message): void
    {
        $logMessage = "-------------------------------------------\n";
        $logMessage .= "MAIL FAIL FALLBACK AT: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "TO: $to\n";
        $logMessage .= "SUBJECT: $subject\n";
        $logMessage .= "MESSAGE: $message\n";
        $logMessage .= "-------------------------------------------\n";

        file_put_contents(__DIR__ . '/../../logs/mail.log', $logMessage, FILE_APPEND);
    }
}
