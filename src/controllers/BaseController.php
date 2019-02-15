<?php
namespace mediabeastnz\abandonedcart\controllers;

use mediabeastnz\abandonedcart\AbandonedCart;
use mediabeastnz\abandonedcart\models\AbandonedCart as CartModel;

use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use craft\commerce\elements\Order;

use yii\web\Response;

class BaseController extends Controller
{

    protected $allowAnonymous = [
        'restore-cart'
    ];

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

        $orders = AbandonedCart::$plugin->carts->getAbandonedOrders($start = 'PT1H', $end = 'PT24H');
        // echo '<pre>'; print_r($orders->count()); echo '</pre>'; die();

        $carts = AbandonedCart::$plugin->carts->getAbandonedCarts();
        return $this->renderTemplate('abandoned-cart/index', array(
            'carts' => $carts
        ));
    }

    // TODO: move logic to service
    public function actionRestoreCart()
    {
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        // find the order
        $number = $request->getParam('number');
        $order = Order::find()->number($number)->one();

        if ($order && !$order->isCompleted){https://www.bobcat.test/abandoned-cart-restore?

            // check if abandoned cart expiry time is valid
            $expiry = AbandonedCart::$plugin->getSettings()->restoreExpiryHours;
            $abandonedCartRecord = AbandonedCart::$plugin->carts->getAbandonedCartByOrderId($order->id);

            if($abandonedCartRecord) {

                $expiredTime = $abandonedCartRecord->dateUpdated;
                $expiredTime->add(new \DateInterval("PT{$expiry}H"));
                $expiredTimestamp = $expiredTime->getTimestamp();

                $now = new \DateTime();
                $nowTimestamp = $now->getTimestamp();

                // if time hasn't expired - yay
                if ($nowTimestamp < $expiredTimestamp) {
        
                    \craft\commerce\Plugin::getInstance()->getCarts()->forgetCart();
                    $session->set('commerce_cart', $number);
                    $session->setNotice('Your cart has been restored');

                    // mark abandoned cart as being clicked/actioned but not completed yet.
                    $abandonedCartRecord->clicked = true;
                    $abandonedCartRecord->save($abandonedCartRecord);

                    return $this->redirect('shop/cart');
                }
            }

        }

        $session->setNotice("Your cart coundn't be restored, it may have expired.");
        return $this->redirect('shop/cart');
    }

}
