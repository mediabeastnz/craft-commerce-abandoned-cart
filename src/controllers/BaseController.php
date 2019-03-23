<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\controllers;

use mediabeastnz\abandonedcart\AbandonedCart;
use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;
use mediabeastnz\abandonedcart\models\AbandonedCart as CartModel;


use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use craft\commerce\elements\Order;
use craft\db\Paginator;
use craft\db\Query;
use craft\web\twig\variables\Paginate;

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
        $c = new Query();
        $c->select('*')->from(['{{%abandonedcart_carts}}'])->orderBy('dateUpdated desc');
        $paginator = new Paginator($c, [
            'pageSize' => 20,
            'currentPage' => \Craft::$app->request->pageNum,
        ]);
        
        $pageResults = $paginator->getPageResults();
        if($pageResults && count($pageResults)) {
            $carts = [];
            foreach ($pageResults as $pageResult) {
                $carts[] = new CartModel($pageResult);
            }

            $pageOffset = $paginator->getPageOffset();
            $page = Paginate::create($paginator);

            return $this->renderTemplate('abandoned-cart/index', [
                'carts' => $carts,
                'pageInfo' => [
                    'first' => $pageOffset + 1,
                    'last' => $pageOffset + count($pageResults),
                    'total' => $paginator->getTotalResults(),
                    'currentPage' => $paginator->getCurrentPage(),
                    'totalPages' => $paginator->getTotalPages(),
                    'prevUrl' => $page->getPrevUrl(),
                    'nextUrl' => $page->getNextUrl(),
                ],
                'totalRecovered' => AbandonedCart::$plugin->carts->getAbandonedCartsRecovered(),
                'conversionRate' => AbandonedCart::$plugin->carts->getAbandondedCartsConversion()
            ]);
        } else {
            return $this->renderTemplate('abandoned-cart/index', [
                'carts' => false,
                'totalRecovered' => AbandonedCart::$plugin->carts->getAbandonedCartsRecovered(),
                'conversionRate' => AbandonedCart::$plugin->carts->getAbandondedCartsConversion()
            ]);
        }  
    }

    public function actionFindCarts()
    {
        $abandonedCarts = AbandonedCart::$plugin->carts->getEmailsToSend();
        Craft::$app->getSession()->setNotice(Craft::t('app', $abandonedCarts . ' abandoned carts were found'));
        return $this->redirect('abandoned-cart/dashboard');
    }

    // TODO: move logic to service
    public function actionRestoreCart()
    {
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        // find the order
        $number = $request->getParam('number');
        $order = Order::find()->number($number)->one();

        if ($order && !$order->isCompleted){

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
