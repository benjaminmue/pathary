<?php declare(strict_types=1);

namespace Movary\Domain\User;

use DateTimeImmutable;

class TrustedDeviceEntity
{
    private function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $tokenHash,
        private readonly string $deviceName,
        private readonly ?string $userAgent,
        private readonly ?string $ipAddress,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $lastUsedAt,
    ) {
    }

    public static function createFromArray(array $data) : self
    {
        return new self(
            (int)$data['id'],
            (int)$data['user_id'],
            $data['token_hash'],
            $data['device_name'],
            $data['user_agent'] ?? null,
            $data['ip_address'] ?? null,
            new DateTimeImmutable($data['expires_at']),
            new DateTimeImmutable($data['created_at']),
            isset($data['last_used_at']) ? new DateTimeImmutable($data['last_used_at']) : null,
        );
    }

    public function getCreatedAt() : DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeviceName() : string
    {
        return $this->deviceName;
    }

    public function getExpiresAt() : DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getIpAddress() : ?string
    {
        return $this->ipAddress;
    }

    public function getLastUsedAt() : ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getTokenHash() : string
    {
        return $this->tokenHash;
    }

    public function getUserAgent() : ?string
    {
        return $this->userAgent;
    }

    public function getUserId() : int
    {
        return $this->userId;
    }

    public function isExpired() : bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }
}
