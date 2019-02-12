<?php
namespace mediabeastnz\abandonedcart\variables;

use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\web\View;

use yii\base\Behavior;

class AbandonedCartVariable
{
    public function getPluginName()
    {
        return AbandonedCart::$plugin->getPluginName();
    }

}
