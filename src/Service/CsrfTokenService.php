<?php declare(strict_types=1);

namespace Movary\Service;

use Movary\Util\SessionWrapper;

class CsrfTokenService
{
    private const string CSRF_TOKEN_SESSION_KEY = 'csrf_token';

    public function __construct(
        private readonly SessionWrapper $sessionWrapper,
    ) {
    }

    public function generateToken() : string
    {
        $token = $this->sessionWrapper->find(self::CSRF_TOKEN_SESSION_KEY);

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            $this->sessionWrapper->set(self::CSRF_TOKEN_SESSION_KEY, $token);
        }

        return $token;
    }

    public function validateToken(?string $token) : bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $storedToken = $this->sessionWrapper->find(self::CSRF_TOKEN_SESSION_KEY);

        if ($storedToken === null) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    public function regenerateToken() : string
    {
        $token = bin2hex(random_bytes(32));
        $this->sessionWrapper->set(self::CSRF_TOKEN_SESSION_KEY, $token);

        return $token;
    }
}
