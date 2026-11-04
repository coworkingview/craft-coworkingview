<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\models;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Rows from the Price Index, with the attribution that has to travel with them.
 *
 * Iterate it in Twig (`{% for row in dataset %}`) and read `attribution`,
 * `license` and `asOf` from the same object.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class Dataset implements IteratorAggregate, Countable
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows,
        public ?string $attribution,
        public ?string $license,
        public ?string $asOf,
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
