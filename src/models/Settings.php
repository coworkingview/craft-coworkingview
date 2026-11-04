<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\models;

use craft\base\Model;

/**
 * Plugin settings stored in project config.
 *
 * Properties stay untyped because Craft assigns the raw control-panel
 * payload before validation runs.
 */
class Settings extends Model
{
    /**
     * Seconds to keep a Price Index response in Craft’s cache.
     * `0` skips the cache.
     *
     * @var int|string
     */
    public $cacheDuration = 3600;

    /**
     * ISO currency to request, or an empty string for each city’s own currency.
     *
     * @var string
     */
    public $currency = '';

    /**
     * @return int seconds
     */
    public function cacheDuration(): int
    {
        return max(0, (int)$this->cacheDuration);
    }

    public function currency(): string
    {
        return strtoupper(trim((string)$this->currency));
    }

    protected function defineRules(): array
    {
        return [
            ...parent::defineRules(),
            [['cacheDuration'], 'integer', 'min' => 0, 'max' => 604800],
            [['currency'], 'in', 'range' => ['', 'EUR', 'GBP', 'USD', 'CHF', 'AED'], 'skipOnEmpty' => true],
        ];
    }
}
