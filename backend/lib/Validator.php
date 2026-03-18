<?php

/**
 * Server-Side Input Validator
 *
 * Validates payment-related inputs before processing.
 * Returns an array of error messages (empty = valid).
 */

declare(strict_types=1);

class Validator
{
    private const MIN_AMOUNT_KOBO = 10000;    // ₦100 minimum
    private const MAX_AMOUNT_KOBO = 100000000; // ₦1,000,000 maximum
    private const ALLOWED_CURRENCIES = ['NGN', 'USD', 'GHS', 'ZAR', 'KES'];

    /**
     * Validate payment initialization inputs.
     *
     * @return string[] Array of error messages. Empty means valid.
     */
    public static function validatePaymentInit(array $data): array
    {
        $errors = [];

        // Email validation
        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address format.';
        }

        // Amount validation
        if (!isset($data['amount']) || $data['amount'] === '') {
            $errors[] = 'Amount is required.';
        } else {
            $amount = (int) $data['amount'];
            if ($amount < self::MIN_AMOUNT_KOBO) {
                $errors[] = sprintf('Amount must be at least %s.', self::formatKobo(self::MIN_AMOUNT_KOBO));
            }
            if ($amount > self::MAX_AMOUNT_KOBO) {
                $errors[] = sprintf('Amount cannot exceed %s.', self::formatKobo(self::MAX_AMOUNT_KOBO));
            }
        }

        // Currency validation (optional field)
        if (!empty($data['currency']) && !in_array(strtoupper($data['currency']), self::ALLOWED_CURRENCIES, true)) {
            $errors[] = 'Unsupported currency. Allowed: ' . implode(', ', self::ALLOWED_CURRENCIES);
        }

        // Full name validation (optional but sanitized)
        if (!empty($data['full_name']) && strlen($data['full_name']) > 255) {
            $errors[] = 'Full name must be 255 characters or fewer.';
        }

        return $errors;
    }

    /**
     * Validate a transaction reference.
     *
     * @return string|null Error message or null if valid.
     */
    public static function validateReference(string $reference): ?string
    {
        if (empty($reference)) {
            return 'Transaction reference is required.';
        }

        if (!preg_match('/^PAY-[0-9]{13}-[a-f0-9]{8}$/', $reference)) {
            return 'Invalid transaction reference format.';
        }

        return null;
    }

    /**
     * Format kobo amount to a readable Naira string.
     */
    private static function formatKobo(int $kobo): string
    {
        return '₦' . number_format($kobo / 100, 2);
    }

    /**
     * Sanitize a string input.
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
