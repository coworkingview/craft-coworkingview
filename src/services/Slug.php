<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use InvalidArgumentException;

/**
 * Normalises city slugs and country codes before they are sent to the API.
 */
final class Slug
{
    public static function city(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', '-', $value) ?? $value;

        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value)) {
            throw new InvalidArgumentException('Invalid city slug.');
        }

        return $value;
    }

    public static function country(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtoupper(trim($value));
        if (!preg_match('/^[A-Z]{2}$/', $value)) {
            throw new InvalidArgumentException('Invalid country code.');
        }

        return $value;
    }
}
