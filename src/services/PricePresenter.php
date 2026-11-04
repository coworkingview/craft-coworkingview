<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use Craft;
use craft\base\Component;

/**
 * Labels and currency formatting for control-panel views.
 */
class PricePresenter extends Component
{
    /** @var array<string, string> */
    private const PRODUCTS = [
        'flex_desk' => 'Flex desk',
        'fix_desk' => 'Dedicated desk',
        'private_office' => 'Private office',
        'exclusive_bereich' => 'Exclusive area',
        'day_pass' => 'Day pass',
        'meeting_room' => 'Meeting room',
        'event_space' => 'Event space',
        'virtual_office' => 'Virtual office',
        'membership' => 'Membership',
    ];

    /** @var array<string, string> */
    private const UNITS = [
        'month' => 'month',
        'day' => 'day',
        'hour' => 'hour',
    ];

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, median: string, spread: string, sample: string, lowConfidence: bool}>
     */
    public function rows(array $rows, string $currency): array
    {
        $rendered = [];
        foreach ($rows as $row) {
            $product = (string)($row['product'] ?? '');
            $unit = $this->stringOrNull($row['unit'] ?? null);
            $sample = $row['sample_size'] ?? null;
            $rendered[] = [
                'label' => $this->productLabel($product),
                'median' => $this->format($this->numberOrNull($row['median'] ?? null), $currency)
                    . ($unit !== null ? ' / ' . $this->unitLabel($unit) : ''),
                'spread' => $this->spread($row, $currency),
                'sample' => is_int($sample) ? (string)$sample : '—',
                'lowConfidence' => (bool)($row['low_confidence'] ?? false),
            ];
        }

        return $rendered;
    }

    public function format(int|float|null $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        $numeric = (float)$amount;
        $isWhole = abs($numeric - round($numeric)) < 0.001;
        $formatted = $isWhole
            ? number_format($numeric, 0, '.', ',')
            : number_format($numeric, 2, '.', ',');

        return match ($currency) {
            'EUR' => '€' . $formatted,
            'GBP' => '£' . $formatted,
            'USD' => '$' . $formatted,
            '' => $formatted,
            default => $formatted . ' ' . $currency,
        };
    }

    public function productLabel(string $product): string
    {
        $label = self::PRODUCTS[$product] ?? str_replace('_', ' ', $product);

        return Craft::t('coworkingview', $label);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function spread(array $row, string $currency): string
    {
        $p25 = $this->numberOrNull($row['p25'] ?? null);
        $p75 = $this->numberOrNull($row['p75'] ?? null);
        if ($p25 === null || $p75 === null) {
            return '—';
        }

        return $this->format($p25, $currency) . ' – ' . $this->format($p75, $currency);
    }

    private function unitLabel(string $unit): string
    {
        return Craft::t('coworkingview', self::UNITS[$unit] ?? $unit);
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
