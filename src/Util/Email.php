<?php

namespace Util;

use PHPMailer\PHPMailer\PHPMailer;
use Rentvine\Logger;
use Throwable;

class Email
{
    public $emailService = null;
    public function __construct()
    {
        $this->getEmailService();
    }

    private function getEmailService(): PHPMailer
    {
        if ($this->emailService == null) {
            $mail = new PHPMailer();
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = Env::getAppEmailUsername();
            $mail->Password = Env::getAppEmailPassword();
            $mail->setFrom(Env::getAppEmailFrom(), 'No Reply');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $this->emailService = $mail;
        }

        return $this->emailService;
    }

    public function send(string $to, string $subject, string $message, bool $htmlContent = true): bool
    {
        try {
            $emailService = $this->getEmailService();
            $emailService->clearAddresses();
            $emailService->clearAllRecipients();
            $emailService->clearAttachments();

            $emailService->addAddress($to);
            $emailService->isHTML($htmlContent);
            $emailService->Subject = $subject;
            $emailService->Body = $message;
            return $emailService->send();
        } catch (Throwable $e) {
            Logger::warning("Error sending email: " . $e->getMessage());
            return false;
        }
    }
}