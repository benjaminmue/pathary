<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Repository\TrustedDeviceRepository;
use Movary\ValueObject\DateTime;

class TrustedDeviceService
{
    private const TRUSTED_DEVICE_EXPIRATION_DAYS = 30;

    public function __construct(
        private readonly TrustedDeviceRepository $trustedDeviceRepository,
    ) {
    }

    public function createTrustedDevice(int $userId, string $deviceName, string $userAgent, ?string $ipAddress = null) : string
    {
        // Generate unique device token
        $deviceToken = bin2hex(random_bytes(32));

        // Generate device fingerprint from user agent and IP
        $deviceFingerprint = $this->generateDeviceFingerprint($userAgent, $ipAddress);

        // Set expiration to 30 days from now
        $expiresAt = DateTime::create()->modify('+' . self::TRUSTED_DEVICE_EXPIRATION_DAYS . ' days');

        $this->trustedDeviceRepository->create($userId, $deviceToken, $deviceName, $deviceFingerprint, $expiresAt);

        return $deviceToken;
    }

    public function verifyTrustedDevice(string $deviceToken) : ?array
    {
        $device = $this->trustedDeviceRepository->findByToken($deviceToken);

        if ($device === null) {
            return null;
        }

        // Check if device is expired
        $expiresAt = DateTime::createFromString($device['expires_at']);
        if ($expiresAt->isInPast() === true) {
            $this->trustedDeviceRepository->delete((int)$device['id']);
            return null;
        }

        // Update last used timestamp
        $this->trustedDeviceRepository->updateLastUsed((int)$device['id']);

        return $device;
    }

    public function getTrustedDevices(int $userId) : array
    {
        return $this->trustedDeviceRepository->findAllByUserId($userId);
    }

    public function revokeTrustedDevice(int $deviceId) : void
    {
        $this->trustedDeviceRepository->delete($deviceId);
    }

    public function revokeAllTrustedDevices(int $userId) : void
    {
        $this->trustedDeviceRepository->deleteAllByUserId($userId);
    }

    public function cleanupExpiredDevices() : void
    {
        $this->trustedDeviceRepository->deleteExpired();
    }

    private function generateDeviceFingerprint(string $userAgent, ?string $ipAddress) : string
    {
        // Create a fingerprint from user agent and IP address
        $data = $userAgent . '|' . ($ipAddress ?? 'unknown');
        return hash('sha256', $data);
    }
}
