<?php declare(strict_types=1);

namespace Movary\Service\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailService
{
    public function __construct(
        private PHPMailer $phpMailer,
    ) {
    }

    public function sendEmail(string $targetEmailAddress, string $subject, string $htmlMessage, SmtpConfig $smtpConfig) : void
    {
        $this->phpMailer->SMTPDebug = SMTP::DEBUG_OFF;

        if ($smtpConfig->getHost() === '') {
            throw new CannotSendEmailException('SMTP host must be set.');
        }

        if ($smtpConfig->getPort() === 0) {
            throw new CannotSendEmailException('SMTP port must be set.');
        }

        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = $smtpConfig->getHost();
        $this->phpMailer->Port = $smtpConfig->getPort();
        $this->phpMailer->setFrom($smtpConfig->getFromAddress());

        // Map encryption value to PHPMailer constants
        $encryption = $smtpConfig->getEncryption();
        if ($encryption === 'tls') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            // No encryption - explicitly set to false
            $this->phpMailer->SMTPSecure = false;
        }

        $this->phpMailer->SMTPAuth = $smtpConfig->isWithAuthentication();
        $this->phpMailer->Username = (string)$smtpConfig->getUser();
        $this->phpMailer->Password = (string)$smtpConfig->getPassword();

        $this->phpMailer->addAddress($targetEmailAddress);
        $this->phpMailer->Subject = $subject;
        $this->phpMailer->Body = $htmlMessage;

        if ($this->phpMailer->send() === false || $this->phpMailer->isError() === true) {
            // Provide more detailed error information
            $errorInfo = $this->phpMailer->ErrorInfo;

            // Add context to common errors
            if (str_contains($errorInfo, 'SMTP connect() failed')) {
                throw new CannotSendEmailException(
                    'SMTP connect() failed. Please verify: ' .
                    '1) SMTP host and port are correct, ' .
                    '2) Encryption setting matches port (587=TLS, 465=SSL), ' .
                    '3) Firewall allows outbound connection. ' .
                    'Error: ' . $errorInfo
                );
            }

            // Microsoft 365 specific error: SMTP AUTH disabled
            if (str_contains($errorInfo, 'SmtpClientAuthentication is disabled')) {
                throw new CannotSendEmailException(
                    'Microsoft 365 SMTP authentication is disabled for your tenant or account. ' .
                    'Your M365 administrator must enable SMTP AUTH. ' .
                    'See: https://aka.ms/smtp_auth_disabled ' .
                    'Error: ' . $errorInfo
                );
            }

            if (str_contains($errorInfo, 'Authentication failed') || str_contains($errorInfo, '535')) {
                throw new CannotSendEmailException(
                    'SMTP authentication failed. Please verify username and password are correct. ' .
                    'For M365, use your full email as username and an app password if MFA is enabled. ' .
                    'Error: ' . $errorInfo
                );
            }

            throw new CannotSendEmailException($errorInfo);
        }
    }
}
