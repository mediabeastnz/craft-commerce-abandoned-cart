<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart;

use mediabeastnz\abandonedcart\models\Settings;
use mediabeastnz\abandonedcart\services\Carts;
use mediabeastnz\abandonedcart\variables\AbandonedCartVariable;

use Craft;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\services\Plugins;
use craft\services\Elements;
use craft\helpers\UrlHelper;
use craft\events\PluginEvent;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;

use yii\base\Event;


class AbandonedCart extends Plugin
{

    public $hasCpSettings = true;

    public static $plugin;

    public function init()
    {
        parent::init();
        
        self::$plugin = $this;

        $this->setComponents([
            'carts' => Carts::class,
        ]);

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'abandoned-cart' => 'abandoned-cart/base/index',
                'abandoned-cart/dashboard' => 'abandoned-cart/base/index',
                'abandoned-cart/settings' => 'abandoned-cart/base/settings'
            ]);
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['abandoned-cart-restore'] = '/abandoned-cart/base/restore-cart';
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );

        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $e) {
            $order = $e->sender;
            $this->carts->markCartAsRecovered($order);
        });

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $variable = $event->sender;
            $variable->set('abandonedcart', AbandonedCartVariable::class);
        });

        Craft::info(
            Craft::t(
                'abandoned-cart',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getPluginName()
    {
        return Craft::t('abandoned-cart', $this->getSettings()->pluginName);
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('abandoned-cart/settings'));
    }

    public function getCpNavItem()
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = $this->getPluginName();

        $navItem['subnav']['dashboard'] = [
            'label' => Craft::t('app', 'Dashboard'),
            'url' => 'abandoned-cart/dashboard'
        ];
        $navItem['subnav']['settings'] = [
            'label' => Craft::t('app', 'Settings'),
            'url' => 'abandoned-cart/settings'
        ];

        return $navItem;
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml()
    {
        return \Craft::$app->getView()->renderTemplate('abandoned-cart/settings', [
            'settings' => $this->getSettings()
        ]);
    }
}