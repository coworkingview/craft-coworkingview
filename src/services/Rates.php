<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use coworkingview\coworkingview\models\CityRate;
use coworkingview\coworkingview\models\Dataset;
use coworkingview\coworkingview\Plugin;
use Craft;
use craft\base\Component;
use InvalidArgumentException;

/**
 * Cached reads of the CoworkingView Price Index.
 */
class Rates extends Component
{
    /** @var list<string> */
    private const CURRENCIES = ['EUR', 'GBP', 'USD', 'CHF', 'AED'];

    private ?string $lastError = null;

    private ?ApiClient $client = null;

    private ?MarketRatesParser $parser = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Published prices for one city, or null when that city has no index yet.
     */
    public function rate(string $city, ?string $currency = null): ?CityRate
    {
        $this->lastError = null;

        try {
            $slug = Slug::city($city);
            $resolved = $this->resolveCurrency($currency);
        } catch (InvalidArgumentException $e) {
            $this->lastError = $e->getMessage() === 'Invalid city slug.'
                ? Craft::t('coworkingview', 'Enter a city slug such as berlin or madrid.')
                : Craft::t('coworkingview', 'Currency must be EUR, GBP, USD, CHF or AED.');

            return null;
        }

        try {
            $payload = $this->cachedRates($slug, null, $resolved);
            $dataset = $this->parser()->rates($payload);
        } catch (ApiException $e) {
            Craft::warning($e->errorCode . ' ' . $e->detail, 'coworkingview');
            $this->lastError = Craft::t('coworkingview', 'CoworkingView could not be reached. Try again in a moment.');

            return null;
        }

        $rows = [];
        foreach ($dataset->rows as $row) {
            if (($row['city_slug'] ?? null) === $slug) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            $this->lastError = Craft::t('coworkingview', 'No published prices for that city yet.');

            return null;
        }

        $products = [];
        foreach ($rows as $row) {
            $product = $row['product'] ?? null;
            if (is_string($product) && $product !== '') {
                $products[$product] = $row;
            }
        }

        $first = $rows[0];

        return new CityRate(
            $slug,
            (string)($first['city_name'] ?? $slug),
            is_string($first['country_code'] ?? null) ? $first['country_code'] : null,
            (string)($first['currency'] ?? ''),
            $dataset->asOf,
            $dataset->attribution,
            $dataset->license,
            $products,
            $rows,
        );
    }

    /**
     * One row per city. Unpublished cities are included with `published = false`.
     */
    public function cities(?string $country = null, ?string $currency = null): Dataset
    {
        $this->lastError = null;

        try {
            $country = Slug::country($country);
            $resolved = $this->resolveCurrency($currency);
        } catch (InvalidArgumentException) {
            $this->lastError = Craft::t('coworkingview', 'Country must be a two-letter code such as DE, and currency must be EUR, GBP, USD, CHF or AED.');

            return new Dataset([], null, null, null);
        }

        try {
            $payload = $this->cachedRates(null, $country, $resolved);
        } catch (ApiException $e) {
            Craft::warning($e->errorCode . ' ' . $e->detail, 'coworkingview');
            $this->lastError = Craft::t('coworkingview', 'CoworkingView could not be reached. Try again in a moment.');

            return new Dataset([], null, null, null);
        }

        return $this->parser()->cities($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedRates(?string $city, ?string $country, ?string $currency): array
    {
        $locale = $this->locale();
        $key = implode(':', [
            'coworkingview',
            'v1',
            'rates',
            $locale,
            $city ?? '*',
            $country ?? '*',
            $currency ?? '*',
        ]);

        $load = function() use ($city, $country, $currency, $locale): array {
            return $this->client()->marketRates($city, $country, $currency, $locale);
        };

        $ttl = Plugin::getInstance()->getSettings()->cacheDuration();
        if ($ttl <= 0) {
            return $load();
        }

        /** @var array<string, mixed> $payload */
        $payload = Craft::$app->getCache()->getOrSet($key, $load, $ttl);

        return $payload;
    }

    private function resolveCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            $currency = Plugin::getInstance()->getSettings()->currency();
        }

        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            return null;
        }

        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new InvalidArgumentException('Invalid currency.');
        }

        return $currency;
    }

    private function locale(): string
    {
        $language = Craft::$app->getSites()->getCurrentSite()->language;
        $short = strtolower(substr($language, 0, 2));

        return in_array($short, ['en', 'de', 'es'], true) ? $short : 'en';
    }

    private function client(): ApiClient
    {
        return $this->client ??= new ApiClient();
    }

    private function parser(): MarketRatesParser
    {
        return $this->parser ??= new MarketRatesParser();
    }
}
