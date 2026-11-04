<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\widgets;

use coworkingview\coworkingview\Plugin;
use Craft;
use craft\base\Widget;

/**
 * Dashboard widget showing one city’s CoworkingView prices.
 */
class PriceWidget extends Widget
{
    public ?string $city = 'berlin';

    public static function displayName(): string
    {
        return Craft::t('coworkingview', 'CoworkingView prices');
    }

    public static function icon(): ?string
    {
        return Plugin::iconMaskSvg();
    }

    public static function maxColspan(): ?int
    {
        return 2;
    }

    public function getTitle(): ?string
    {
        return Craft::t('coworkingview', 'CoworkingView');
    }

    public function getSubtitle(): ?string
    {
        $city = trim((string)$this->city);

        return $city !== '' ? $city : null;
    }

    public function getBodyHtml(): ?string
    {
        $city = trim((string)$this->city);
        $rates = Plugin::getInstance()->getRates();
        $rate = $city === '' ? null : $rates->rate($city);
        $rows = [];
        if ($rate !== null) {
            $rows = Plugin::getInstance()->getPresenter()->rows($rate->rows, $rate->currency);
        }

        return Craft::$app->getView()->renderTemplate('coworkingview/widget', [
            'rate' => $rate,
            'rows' => $rows,
            'error' => $rate === null ? ($rates->getLastError() ?? Craft::t('coworkingview', 'Choose a city in the widget settings.')) : null,
            'locale' => $this->locale(),
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('coworkingview/widget-settings', [
            'widget' => $this,
        ]);
    }

    protected function defineRules(): array
    {
        return [
            ...parent::defineRules(),
            [['city'], 'trim'],
            [['city'], 'required'],
            [['city'], 'match', 'pattern' => '/^[A-Za-z0-9][A-Za-z0-9._\- ]*$/'],
        ];
    }

    private function locale(): string
    {
        $short = strtolower(substr(Craft::$app->getSites()->getCurrentSite()->language, 0, 2));

        return in_array($short, ['en', 'de', 'es'], true) ? $short : 'en';
    }
}
