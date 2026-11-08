<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * Rate limit buckets for sensitive web endpoints. Each route opts into exactly one
 * bucket through its RateLimited* middleware, so limits no longer depend on
 * substring matches against the request path.
 *
 * Limits can be overridden with RATE_LIMIT_<NAME>_MAX and RATE_LIMIT_<NAME>_WINDOW
 * (seconds), e.g. RATE_LIMIT_PASSWORD_CHANGE_MAX=10.
 */
enum RateLimitPolicy : string
{
    case PasswordChange = 'password_change';
    case UserCreate = 'user_create';
    case TestEmail = 'test_email';
    case PasswordSetup = 'password_setup';
    case InitialSetup = 'initial_setup';

    /**
     * Authenticated endpoints are limited per user; anonymous ones per client IP.
     */
    public function isPerUser() : bool
    {
        return match ($this) {
            self::PasswordChange, self::UserCreate, self::TestEmail => true,
            self::PasswordSetup, self::InitialSetup => false,
        };
    }

    public function maxAttempts() : int
    {
        return $this->readEnvironmentOverride('MAX', match ($this) {
            self::PasswordChange, self::TestEmail => 5,
            self::UserCreate, self::PasswordSetup => 10,
            // The setup wizard posts four steps per run.
            self::InitialSetup => 20,
        });
    }

    public function windowSeconds() : int
    {
        return $this->readEnvironmentOverride('WINDOW', match ($this) {
            self::UserCreate => 60,
            self::PasswordChange, self::TestEmail, self::PasswordSetup, self::InitialSetup => 300,
        });
    }

    private function readEnvironmentOverride(string $suffix, int $default) : int
    {
        $value = getenv('RATE_LIMIT_' . strtoupper($this->value) . '_' . $suffix);

        if ($value === false || (int)$value < 1) {
            return $default;
        }

        return (int)$value;
    }
}
