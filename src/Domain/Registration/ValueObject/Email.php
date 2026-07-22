<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\ValueObject;

/**
 * Matches legacy's own normalization exactly:
 * filter_var(strtolower($_POST['user_name']), FILTER_SANITIZE_EMAIL)
 * (process_users_register.inc.php:23) - lowercase first, then sanitize.
 */
final class Email
{
    private function __construct(private string $value)
    {
    }

    public static function from(string $raw): self
    {
        $trimmed = trim($raw);
        $normalized = (string) filter_var(strtolower($trimmed), FILTER_SANITIZE_EMAIL);

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address: ' . $raw);
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
