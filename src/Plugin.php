<?php

declare(strict_types=1);

namespace coworkingview\coworkingview;

use coworkingview\coworkingview\models\Settings;
use coworkingview\coworkingview\services\PricePresenter;
use coworkingview\coworkingview\services\Rates;
use coworkingview\coworkingview\utilities\PriceUtility;
use coworkingview\coworkingview\variables\CoworkingViewVariable;
use coworkingview\coworkingview\widgets\PriceWidget;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Dashboard;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * Flexible office prices from the CoworkingView Price Index.
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 *
 * @author CoworkingView
 * @license MIT
 * @link https://coworkingview.com
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'rates' => Rates::class,
                'presenter' => PricePresenter::class,
            ],
        ];
    }

    public static function iconSvg(): string
    {
        return __DIR__ . '/icon.svg';
    }

    public static function iconMaskSvg(): string
    {
        return __DIR__ . '/icon-mask.svg';
    }

    public function init(): void
    {
        parent::init();

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('coworkingview', CoworkingViewVariable::class);
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event): void {
                $event->types[] = PriceWidget::class;
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event): void {
                $event->types[] = PriceUtility::class;
            }
        );
    }

    public function getRates(): Rates
    {
        /** @var Rates $rates */
        $rates = $this->get('rates');

        return $rates;
    }

    public function getPresenter(): PricePresenter
    {
        /** @var PricePresenter $presenter */
        $presenter = $this->get('presenter');

        return $presenter;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('coworkingview/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
