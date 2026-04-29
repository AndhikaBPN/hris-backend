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
            $mail->SMTPDebug  = 0; // Disable debug output
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['MAIL_PORT'] ?? 587;

            // Recipients
            $mail->setFrom(
                $_ENV['MAIL_FROM'] ?? 'no-reply@hris.local', 
                $_ENV['MAIL_FROM_NAME'] ?? 'HRIS System'
            );
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($message);
            $mail->AltBody = strip_tags($message);

            $mail->send();
            return true;
        } catch (Exception $e) {
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
