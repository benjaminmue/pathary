<?php declare(strict_types=1);

namespace Movary\Service\Email;

use Doctrine\DBAL\Connection;
use Movary\Service\Email\Exception\EmailRateLimitExceededException;

/**
 * Service for rate limiting email sends to prevent spam and abuse
 */
class EmailRateLimiterService
{
    // Rate limit constants (from Issue 31)
    private const int MAX_EMAILS_PER_RECIPIENT_PER_HOUR = 5;
    private const int MAX_EMAILS_PER_ADMIN_PER_HOUR = 20;
    private const int MAX_EMAILS_PER_ADMIN_PER_DAY = 100;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * Check if an email can be sent, enforcing rate limits
     *
     * @throws EmailRateLimitExceededException if rate limit exceeded
     */
    public function checkRateLimit(string $recipientEmail, ?int $senderUserId = null): void
    {
        // Check 1: Max emails per recipient per hour
        $this->checkRecipientRateLimit($recipientEmail);

        // Check 2 & 3: If sender is specified (admin), check admin limits
        if ($senderUserId !== null) {
            $this->checkAdminHourlyRateLimit($senderUserId);
            $this->checkAdminDailyRateLimit($senderUserId);
        }
    }

    /**
     * Log an email send attempt
     */
    public function logEmailSend(
        string $recipientEmail,
        string $emailType,
        ?int $senderUserId = null,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $this->dbConnection->insert('email_send_log', [
            'recipient_email' => $recipientEmail,
            'email_type' => $emailType,
            'sender_user_id' => $senderUserId,
            'sent_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'success' => $success ? 1 : 0,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if recipient has received too many emails in the last hour
     *
     * @throws EmailRateLimitExceededException
     */
    private function checkRecipientRateLimit(string $recipientEmail): void
    {
        $oneHourAgo = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');

        $count = (int)$this->dbConnection->fetchOne(
            'SELECT COUNT(*) FROM email_send_log
             WHERE recipient_email = :email
             AND sent_at > :since
             AND success = 1',
            [
                'email' => $recipientEmail,
                'since' => $oneHourAgo,
            ]
        );

        if ($count >= self::MAX_EMAILS_PER_RECIPIENT_PER_HOUR) {
            throw EmailRateLimitExceededException::forRecipient($recipientEmail, self::MAX_EMAILS_PER_RECIPIENT_PER_HOUR);
        }
    }

    /**
     * Check if admin has sent too many emails in the last hour
     *
     * @throws EmailRateLimitExceededException
     */
    private function checkAdminHourlyRateLimit(int $senderUserId): void
    {
        $oneHourAgo = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');

        $count = (int)$this->dbConnection->fetchOne(
            'SELECT COUNT(*) FROM email_send_log
             WHERE sender_user_id = :userId
             AND sent_at > :since
             AND success = 1',
            [
                'userId' => $senderUserId,
                'since' => $oneHourAgo,
            ]
        );

        if ($count >= self::MAX_EMAILS_PER_ADMIN_PER_HOUR) {
            throw EmailRateLimitExceededException::forAdminHourly(self::MAX_EMAILS_PER_ADMIN_PER_HOUR);
        }
    }

    /**
     * Check if admin has sent too many emails in the last day
     *
     * @throws EmailRateLimitExceededException
     */
    private function checkAdminDailyRateLimit(int $senderUserId): void
    {
        $oneDayAgo = (new \DateTime())->modify('-1 day')->format('Y-m-d H:i:s');

        $count = (int)$this->dbConnection->fetchOne(
            'SELECT COUNT(*) FROM email_send_log
             WHERE sender_user_id = :userId
             AND sent_at > :since
             AND success = 1',
            [
                'userId' => $senderUserId,
                'since' => $oneDayAgo,
            ]
        );

        if ($count >= self::MAX_EMAILS_PER_ADMIN_PER_DAY) {
            throw EmailRateLimitExceededException::forAdminDaily(self::MAX_EMAILS_PER_ADMIN_PER_DAY);
        }
    }

    /**
     * Get count of emails sent to a recipient in the last hour
     */
    public function getEmailCountForRecipient(string $recipientEmail): int
    {
        $oneHourAgo = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');

        return (int)$this->dbConnection->fetchOne(
            'SELECT COUNT(*) FROM email_send_log
             WHERE recipient_email = :email
             AND sent_at > :since
             AND success = 1',
            [
                'email' => $recipientEmail,
                'since' => $oneHourAgo,
            ]
        );
    }
}
