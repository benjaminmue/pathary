<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Repository\TrustedDeviceRepository;
use Movary\Domain\User\TrustedDeviceEntity;
use Movary\Util\DeviceNameParser;
use Movary\ValueObject\DateTime;

class TrustedDeviceService
{
    private const TRUSTED_DEVICE_EXPIRATION_DAYS = 30;
    private const MAX_TRUSTED_DEVICES_PER_USER = 10;

    public function __construct(
        private readonly TrustedDeviceRepository $trustedDeviceRepository,
    ) {
    }

    public function cleanupExpiredDevices() : void
    {
        $this->trustedDeviceRepository->deleteExpired();
    }

    public function createTrustedDevice(int $userId, ?string $deviceName = null, ?string $userAgent = null, ?string $ipAddress = null) : string
    {
        error_log('[TRUSTED_DEVICE_DEBUG] TrustedDeviceService::createTrustedDevice called for user: ' . $userId);

        // Generate unique random token
        $token = bin2hex(random_bytes(32));
        error_log('[TRUSTED_DEVICE_DEBUG] Token generated: YES (length: ' . strlen($token) . ')');

        // Hash the token for storage (never store plaintext)
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        error_log('[TRUSTED_DEVICE_DEBUG] Token hashed: YES');

        // Parse device name from user agent if not provided
        if ($deviceName === null || $deviceName === '') {
            $deviceName = DeviceNameParser::parse($userAgent);
        }
        error_log('[TRUSTED_DEVICE_DEBUG] Device name: ' . ($deviceName ?? 'null'));

        // Set expiration to 30 days from now
        $expiresAt = DateTime::create()->modify('+' . self::TRUSTED_DEVICE_EXPIRATION_DAYS . ' days');
        error_log('[TRUSTED_DEVICE_DEBUG] Expiration set: ' . $expiresAt->format('Y-m-d H:i:s'));

        // Create the trusted device record
        try {
            $this->trustedDeviceRepository->create($userId, $tokenHash, $deviceName, $userAgent, $ipAddress, $expiresAt);
            error_log('[TRUSTED_DEVICE_DEBUG] Database insert: SUCCESS');
        } catch (\Throwable $e) {
            error_log('[TRUSTED_DEVICE_DEBUG] Database insert FAILED: ' . $e->getMessage());
            throw $e;
        }

        // Enforce limit on trusted devices per user
        $this->enforceTrustedDeviceLimit($userId);
        error_log('[TRUSTED_DEVICE_DEBUG] Device limit enforced');

        // Return the plaintext token (only time it's accessible)
        return $token;
    }

    public function getTrustedDevices(int $userId) : array
    {
        return $this->trustedDeviceRepository->findAllByUserId($userId);
    }

    public function revokeAllTrustedDevices(int $userId) : void
    {
        $this->trustedDeviceRepository->deleteAllByUserId($userId);
    }

    public function revokeTrustedDevice(int $deviceId) : void
    {
        $this->trustedDeviceRepository->delete($deviceId);
    }

    public function verifyTrustedDevice(string $token, int $userId) : ?TrustedDeviceEntity
    {
        // Get all devices for this user
        $devices = $this->trustedDeviceRepository->findAllByUserId($userId);

        foreach ($devices as $device) {
            // Verify the token against the hash
            if (password_verify($token, $device->getTokenHash()) === false) {
                continue;
            }

            // Check if device is expired
            if ($device->isExpired() === true) {
                $this->trustedDeviceRepository->delete($device->getId());
                return null;
            }

            // Update last used timestamp
            $this->trustedDeviceRepository->updateLastUsed($device->getId());

            return $device;
        }

        return null;
    }

    private function enforceTrustedDeviceLimit(int $userId) : void
    {
        $devices = $this->trustedDeviceRepository->findAllByUserId($userId);

        if (count($devices) <= self::MAX_TRUSTED_DEVICES_PER_USER) {
            return;
        }

        // Delete oldest devices beyond the limit
        // Devices are already ordered by created_at DESC, so take from the end
        $devicesToDelete = array_slice($devices, self::MAX_TRUSTED_DEVICES_PER_USER);

        foreach ($devicesToDelete as $device) {
            $this->trustedDeviceRepository->delete($device->getId());
        }
    }
}
