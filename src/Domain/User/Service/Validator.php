<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Exception\EmailNotUnique;
use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Exception\PasswordTooShort;
use Movary\Domain\User\Exception\UsernameInvalidFormat;
use Movary\Domain\User\Exception\UsernameNotUnique;
use Movary\Domain\User\UserRepository;

class Validator
{
    private const int PASSWORD_MIN_LENGTH = 10;

    public function __construct(private readonly UserRepository $repository)
    {
    }

    public function ensureEmailIsUnique(string $email, ?int $expectUserId = null) : void
    {
        $user = $this->repository->findUserByEmail($email);
        if ($user === null) {
            return;
        }

        if ($user->getId() !== $expectUserId) {
            throw new EmailNotUnique();
        }
    }

    public function ensureNameFormatIsValid(string $name) : void
    {
        preg_match('~^[a-zA-Z0-9]+$~', $name, $matches);
        if (empty($matches) === true) {
            throw new UsernameInvalidFormat();
        }
    }

    public function ensureNameIsUnique(string $name, ?int $expectUserId = null) : void
    {
        $user = $this->repository->findUserByName($name);
        if ($user === null) {
            return;
        }

        if ($user->getId() !== $expectUserId) {
            throw new UsernameNotUnique();
        }
    }

    /**
     * Validates password against comprehensive security policy:
     * - Minimum 10 characters
     * - At least 1 uppercase letter
     * - At least 1 lowercase letter
     * - At least 1 number
     * - At least 1 special character
     *
     * @throws PasswordPolicyViolation
     */
    public function ensurePasswordIsValid(string $password) : void
    {
        $violations = [];

        // Check minimum length
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            throw PasswordPolicyViolation::tooShort(self::PASSWORD_MIN_LENGTH);
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'missing uppercase letter';
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'missing lowercase letter';
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'missing number';
        }

        // Check for at least one special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $violations[] = 'missing special character';
        }

        if (!empty($violations)) {
            throw PasswordPolicyViolation::create($violations);
        }
    }

    /**
     * Validates password and returns list of violations (for client-side feedback)
     * Returns empty array if password is valid
     */
    public function getPasswordPolicyViolations(string $password) : array
    {
        $violations = [];

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $violations[] = "Minimum " . self::PASSWORD_MIN_LENGTH . " characters required";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'At least one uppercase letter required';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'At least one lowercase letter required';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'At least one number required';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $violations[] = 'At least one special character required';
        }

        return $violations;
    }
}
