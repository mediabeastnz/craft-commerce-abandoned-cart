<?php
namespace mediabeastnz\abandonedcart\controllers;

use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;

use yii\web\Response;

class BaseController extends Controller
{


    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = AbandonedCart::$plugin->getSettings();

        return $this->renderTemplate('abandoned-cart/settings', array(
            'title' => 'Settings',
            'settings' => $settings,
        ));
    }

    public function actionIndex()
    {
        $carts = false;
        return $this->renderTemplate('abandoned-cart/index', array(
            'carts' => $carts
        ));
    }

}
