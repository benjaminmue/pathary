<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web\Middleware;

use Movary\Util\SessionWrapper;

/**
 * Session stand-in for middleware tests: keeps values in memory instead of
 * starting a real PHP session (which would send headers in the test runner).
 */
class InMemorySessionWrapper extends SessionWrapper
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function find(string $key) : mixed
    {
        return $this->values[$key] ?? null;
    }

    public function has(string $key) : bool
    {
        return isset($this->values[$key]) === true;
    }

    public function set(string $key, mixed $value) : void
    {
        $this->values[$key] = $value;
    }

    public function start() : void
    {
    }

    public function unset(string ...$keys) : void
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }
    }
}
