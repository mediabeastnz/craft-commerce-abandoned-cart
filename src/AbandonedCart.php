<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart;

use mediabeastnz\abandonedcart\models\Settings;
use mediabeastnz\abandonedcart\services\Carts;
use mediabeastnz\abandonedcart\variables\AbandonedCartVariable;
use mediabeastnz\abandonedcart\widgets\TotalCartsRecovered;

use Craft;
use craft\base\Plugin;
use craft\services\Dashboard;
use craft\web\UrlManager;
use craft\services\Plugins;
use craft\services\Elements;
use craft\helpers\UrlHelper;
use craft\events\PluginEvent;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\services\UserPermissions;
use craft\events\RegisterUserPermissionsEvent;

use yii\base\Event;


class AbandonedCart extends Plugin
{

    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

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
                'abandoned-cart/settings' => 'abandoned-cart/base/settings',
                'abandoned-cart/find-carts' => 'abandoned-cart/base/index',
                'abandoned-cart/export' => 'abandoned-cart/base/export'
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

        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = TotalCartsRecovered::class;
        });

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('abandoned-cart', 'Abandoned Carts'),
                    'permissions' => [
                        'abandoned-cart-manageAbandonedCartsSettings' => [
                            'label' => Craft::t('abandoned-cart', 'Manage abandoned cart settings'),
                        ],
                    ],
                ];
            }
        );


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

    public function getSettingsResponse(): mixed
    {
        $url = \craft\helpers\UrlHelper::cpUrl('abandoned-cart/settings');
        return \Craft::$app->controller->redirect($url);
    }

    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = $this->getPluginName();
        
        if ( Craft::$app->getConfig()->general->allowAdminChanges ) {
            
            $navItem['subnav']['dashboard'] = [
                'label' => Craft::t('app', 'Dashboard'),
                'url' => 'abandoned-cart/dashboard'
            ];

            if (Craft::$app->getUser()->checkPermission('abandoned-cart-manageAbandonedCartsSettings')) {
                $navItem['subnav']['settings'] = [
                    'label' => Craft::t('app', 'Settings'),
                    'url' => 'abandoned-cart/settings'
                ];
            }
            
            return $navItem;
        }

        $navItem['url'] = 'abandoned-cart/dashboard';

        return $navItem;
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return \Craft::$app->getView()->renderTemplate('abandoned-cart/settings', [
            'settings' => $this->getSettings()
        ]);
    }
}
