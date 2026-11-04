<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\variables;

use coworkingview\coworkingview\models\CityRate;
use coworkingview\coworkingview\models\Dataset;
use coworkingview\coworkingview\Plugin;

/**
 * `craft.coworkingview` in Twig.
 */
class CoworkingViewVariable
{
    /**
     * Prices for one city. `berlin` and `Berlin` both resolve to the slug `berlin`.
     *
     * Pass a currency (`EUR`, `GBP`, `USD`, `CHF`, `AED`) to override the plugin setting.
     */
    public function rate(string $city, ?string $currency = null): ?CityRate
    {
        return Plugin::getInstance()->getRates()->rate($city, $currency);
    }

    /**
     * One row per city. Pass an ISO country code such as `DE` to limit the list.
     */
    public function cities(?string $country = null, ?string $currency = null): Dataset
    {
        return Plugin::getInstance()->getRates()->cities($country, $currency);
    }

    /**
     * Format an amount with the currency’s symbol when that symbol is unambiguous.
     */
    public function format(mixed $amount, ?string $currency = null): string
    {
        $number = null;
        if (is_int($amount) || is_float($amount)) {
            $number = $amount;
        } elseif (is_string($amount) && is_numeric($amount)) {
            $number = str_contains($amount, '.') ? (float)$amount : (int)$amount;
        }

        return Plugin::getInstance()->getPresenter()->format($number, strtoupper(trim((string)$currency)));
    }
}
