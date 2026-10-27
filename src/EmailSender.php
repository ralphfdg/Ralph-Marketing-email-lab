<?php

declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

final class EmailSender
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings (SMTP connection configuration provided)
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->config['port'];

            // Recipients
            $mail->setFrom(
                $this->config['from_address'],
                'Marketing Campaign Team'
            );

            // TODO E1: Add the customer's email and name as the recipient.
            $mail->addAddress(
                (string) ($customer['email'] ?? ''),
                (string) ($customer['full_name'] ?? '')
            );

            // Content
            $mail->isHTML(false);

            // TODO E2: Set the campaign subject and a personalized plain-text body
            // that clearly displays the persisted promoCode from the job.
            $mail->Subject = (string) ($job['subject'] ?? 'Laboratory promotion');
            
            $promoCode = (string) ($job['promoCode'] ?? 'PROMO15');
            $fullName  = (string) ($customer['full_name'] ?? 'Valued Customer');
            $bodyContent = (string) ($job['body'] ?? "Hello {$fullName},\nYou have been selected to receive our promotional offer.\nUse promo code: {$promoCode}");

            $mail->Body = $bodyContent;

            $mail->send();

            // Return generated Message-ID or fallback
            return $mail->getLastMessageID() ?: ('mailtrap-' . bin2hex(random_bytes(8)));
        } catch (MailerException $e) {
            throw new \RuntimeException("Failed to send email via Mailtrap SMTP: {$mail->ErrorInfo}", 0, $e);
        }
    }
}
