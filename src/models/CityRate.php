<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\models;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Published prices for one city.
 *
 * `products` is keyed by product slug (`flex_desk`, `fix_desk`, …).
 * `rows` is the same data in Price Index order. Iterating the object yields rows.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class CityRate implements IteratorAggregate, Countable
{
    /**
     * @param array<string, array<string, mixed>> $products
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public string $citySlug,
        public string $cityName,
        public ?string $countryCode,
        public string $currency,
        public ?string $asOf,
        public ?string $attribution,
        public ?string $license,
        public array $products,
        public array $rows,
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
