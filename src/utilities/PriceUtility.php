<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\utilities;

use coworkingview\coworkingview\Plugin;
use Craft;
use craft\base\Utility;

/**
 * Control-panel lookup for one city’s flexible office prices.
 */
class PriceUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('coworkingview', 'CoworkingView prices');
    }

    public static function id(): string
    {
        return 'coworkingview-prices';
    }

    public static function icon(): ?string
    {
        return Plugin::iconMaskSvg();
    }

    /**
     * Craft 4 reads the utility icon from this method.
     */
    public static function iconPath(): ?string
    {
        return Plugin::iconMaskSvg();
    }

    public static function contentHtml(): string
    {
        $request = Craft::$app->getRequest();
        $city = $request->getQueryParam('city', 'berlin');
        if (!is_string($city) || trim($city) === '') {
            $city = 'berlin';
        }

        $rates = Plugin::getInstance()->getRates();
        $rate = $rates->rate($city);
        $rows = [];
        if ($rate !== null) {
            $rows = Plugin::getInstance()->getPresenter()->rows($rate->rows, $rate->currency);
        }

        $language = Craft::$app->getSites()->getCurrentSite()->language;
        $short = strtolower(substr($language, 0, 2));
        $locale = in_array($short, ['en', 'de', 'es'], true) ? $short : 'en';

        return Craft::$app->getView()->renderTemplate('coworkingview/utility', [
            'city' => $city,
            'rate' => $rate,
            'rows' => $rows,
            'error' => $rate === null ? $rates->getLastError() : null,
            'locale' => $locale,
        ]);
    }
}
