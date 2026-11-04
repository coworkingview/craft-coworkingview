<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use coworkingview\coworkingview\models\Dataset;

/**
 * Turns a `GET /v1/market/rates` payload into Price Index rows.
 *
 * The column rules match the shared flex-office-data specification used by the
 * Python, R, Java and command-line clients.
 */
final class MarketRatesParser
{
    /** @var list<string> */
    private const PRODUCT_ORDER = [
        'flex_desk',
        'fix_desk',
        'private_office',
        'exclusive_bereich',
        'day_pass',
        'meeting_room',
        'event_space',
        'virtual_office',
        'membership',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function rates(array $payload): Dataset
    {
        $data = $this->data($payload);
        $asOf = $this->stringOrNull($data['asOf'] ?? null);
        $rows = [];

        foreach ($this->listOfArrays($data['cities'] ?? null) as $city) {
            foreach ($this->rateRowsForCity($city, $asOf) as $row) {
                $rows[] = $row;
            }
        }

        $order = array_flip(self::PRODUCT_ORDER);
        usort($rows, function(array $a, array $b) use ($order): int {
            $byCity = strcmp((string)$a['city_slug'], (string)$b['city_slug']);
            if ($byCity !== 0) {
                return $byCity;
            }

            $aRank = $order[$a['product']] ?? 1000;
            $bRank = $order[$b['product']] ?? 1000;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return strcmp((string)$a['product'], (string)$b['product']);
        });

        return $this->dataset($rows, $data, $asOf);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function cities(array $payload): Dataset
    {
        $data = $this->data($payload);
        $asOf = $this->stringOrNull($data['asOf'] ?? null);
        $rows = [];

        foreach ($this->listOfArrays($data['cities'] ?? null) as $city) {
            $rows[] = $this->cityRow($city, true, $asOf);
        }
        foreach ($this->listOfArrays($data['unpublished'] ?? null) as $city) {
            $rows[] = $this->cityRow($city, false, $asOf);
        }

        usort($rows, function(array $a, array $b): int {
            if ($a['published'] !== $b['published']) {
                return $a['published'] ? -1 : 1;
            }

            return strcmp((string)$a['city_slug'], (string)$b['city_slug']);
        });

        return $this->dataset($rows, $data, $asOf);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function data(array $payload): array
    {
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return $payload;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $data
     */
    private function dataset(array $rows, array $data, ?string $asOf): Dataset
    {
        return new Dataset(
            $rows,
            $this->stringOrNull($data['attribution'] ?? null),
            $this->stringOrNull($data['licenseUrl'] ?? null),
            $asOf,
        );
    }

    /**
     * @param array<string, mixed> $city
     * @return list<array<string, mixed>>
     */
    private function rateRowsForCity(array $city, ?string $asOf): array
    {
        $base = [
            'city_slug' => (string)($this->nestedString($city, 'city', 'slug') ?? ''),
            'city_name' => (string)($this->nestedString($city, 'city', 'name') ?? ''),
            'country_code' => $this->stringOrNull($city['countryCode'] ?? null),
            'currency' => (string)($this->stringOrNull($city['currency'] ?? null) ?? ''),
            'as_of' => $asOf,
        ];

        if ($base['city_slug'] === '') {
            return [];
        }

        $rows = [];
        $figures = $this->listOfArrays($city['figures'] ?? null);
        if ($figures !== []) {
            foreach ($figures as $figure) {
                $row = $this->figureRow($base, $figure, 'month', true);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        } else {
            foreach ($this->listOfArrays($city['byType'] ?? null) as $figure) {
                $row = $this->figureRow($base, $figure, 'month', false);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        foreach ($city as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (!isset($value['median'], $value['sampleSize']) || !is_array($value['median'])) {
                continue;
            }
            $product = $this->snake($key);
            $median = $this->amount($value['median']);
            $sample = $this->intOrNull($value['sampleSize']);
            if ($median === null || $sample === null || $product === '') {
                continue;
            }
            $rows[] = array_merge($base, [
                'product' => $product,
                'unit' => $this->unitFor($product),
                'median' => $median,
                'p25' => $this->amount(is_array($value['p25'] ?? null) ? $value['p25'] : null),
                'p75' => $this->amount(is_array($value['p75'] ?? null) ? $value['p75'] : null),
                'sample_size' => $sample,
                'property_count' => $this->intOrNull($value['propertyCount'] ?? null),
                'operator_count' => $this->intOrNull($value['operatorCount'] ?? null),
                'low_confidence' => $sample < 10,
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $figure
     * @return array<string, mixed>|null
     */
    private function figureRow(array $base, array $figure, string $unit, bool $full): ?array
    {
        $product = $this->stringOrNull($figure['optionType'] ?? null);
        $median = $this->amount(is_array($figure['median'] ?? null) ? $figure['median'] : null);
        $sample = $this->intOrNull($figure['sampleSize'] ?? null);
        if ($product === null || $product === '' || $median === null || $sample === null) {
            return null;
        }

        return array_merge($base, [
            'product' => $product,
            'unit' => $unit,
            'median' => $median,
            'p25' => $full ? $this->amount(is_array($figure['p25'] ?? null) ? $figure['p25'] : null) : null,
            'p75' => $full ? $this->amount(is_array($figure['p75'] ?? null) ? $figure['p75'] : null) : null,
            'sample_size' => $sample,
            'property_count' => $full ? $this->intOrNull($figure['propertyCount'] ?? null) : null,
            'operator_count' => $full ? $this->intOrNull($figure['operatorCount'] ?? null) : null,
            'low_confidence' => $sample < 10,
        ]);
    }

    /**
     * @param array<string, mixed> $city
     * @return array<string, mixed>
     */
    private function cityRow(array $city, bool $published, ?string $asOf): array
    {
        return [
            'city_slug' => (string)($this->nestedString($city, 'city', 'slug') ?? ''),
            'city_name' => (string)($this->nestedString($city, 'city', 'name') ?? ''),
            'country_code' => $this->stringOrNull($city['countryCode'] ?? null),
            'currency' => (string)($this->stringOrNull($city['currency'] ?? null) ?? ''),
            'published' => $published,
            'property_count' => $this->intOrNull($city['propertyCount'] ?? null),
            'operator_count' => $this->intOrNull($city['operatorCount'] ?? null),
            'entry_median' => $this->amount(is_array($city['entryMedian'] ?? null) ? $city['entryMedian'] : null),
            'entry_min' => $this->amount(is_array($city['entryMin'] ?? null) ? $city['entryMin'] : null),
            'entry_max' => $this->amount(is_array($city['entryMax'] ?? null) ? $city['entryMax'] : null),
            'p25' => $this->amount(is_array($city['p25'] ?? null) ? $city['p25'] : null),
            'p75' => $this->amount(is_array($city['p75'] ?? null) ? $city['p75'] : null),
            'sample_size' => $published ? $this->intOrNull($city['sampleSize'] ?? null) : null,
            'as_of' => $asOf,
        ];
    }

    private function unitFor(string $product): ?string
    {
        return match ($product) {
            'day_pass' => 'day',
            'meeting_room', 'event_space' => 'hour',
            'virtual_office' => 'month',
            default => null,
        };
    }

    private function snake(string $key): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $key);

        return strtolower($snake ?? $key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOfArrays(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $rows[] = $item;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function nestedString(array $source, string $key, string $child): ?string
    {
        $node = $source[$key] ?? null;
        if (!is_array($node)) {
            return null;
        }

        return $this->stringOrNull($node[$child] ?? null);
    }

    /**
     * @param array<string, mixed>|null $node
     */
    private function amount(?array $node): int|float|null
    {
        if ($node === null || !isset($node['amount']) || !is_numeric($node['amount'])) {
            return null;
        }

        $amount = $node['amount'];
        if (is_string($amount)) {
            return str_contains($amount, '.') ? (float)$amount : (int)$amount;
        }

        return $amount;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int)$value;
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
