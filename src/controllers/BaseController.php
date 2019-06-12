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
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class BaseController extends Controller
{

    protected $allowAnonymous = [
        'restore-cart',
        'find-carts'
    ];

    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $this->requireAdmin();

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
                'conversionRate' => AbandonedCart::$plugin->carts->getAbandondedCartsConversion(),
                'passKey' => AbandonedCart::$plugin->getSettings()->passKey
            ]);
        } else {
            return $this->renderTemplate('abandoned-cart/index', [
                'carts' => false,
                'totalRecovered' => AbandonedCart::$plugin->carts->getAbandonedCartsRecovered(),
                'conversionRate' => AbandonedCart::$plugin->carts->getAbandondedCartsConversion(),
                'passKey' => AbandonedCart::$plugin->getSettings()->passKey
            ]);
        }  
    }

    public function actionFindCarts()
    {
        $request = Craft::$app->getRequest();
        $passkeyFromRequest = $request->getParam('passkey');
        $browser = AbandonedCart::$plugin->carts->getBrowserName($request->getUserAgent());

        // get settings
        $testMode = AbandonedCart::$plugin->getSettings()->testMode;
        $passKey = AbandonedCart::$plugin->getSettings()->passKey;
        $firstTemplate = AbandonedCart::$plugin->getSettings()->firstReminderTemplate;
        $secondTemplate = AbandonedCart::$plugin->getSettings()->secondReminderTemplate;
        $firstSubject = AbandonedCart::$plugin->getSettings()->firstReminderSubject;
        $secondSubject = AbandonedCart::$plugin->getSettings()->secondReminderSubject;

        // check if passkey in url matches settings
        $proceed = $passkeyFromRequest == $passKey;

        if ($proceed) {

            // bypass the queue (test mode is enabled)
            if ($testMode) {
                AbandonedCart::$plugin->carts->getEmailsToSend();
                $abandonedCarts = AbandonedCart::$plugin->carts->getAbandonedCarts();
                $totalCarts = 0;
                if (count($abandonedCarts) > 0) {
                    foreach ($abandonedCarts as $cart) {
                        if ($cart && $cart->isRecovered == 0) {

                            // First Reminder
                            if ($cart->firstReminder == 0) {
                                $totalCarts++;
                                $cart->firstReminder = 1;
                                $cart->isScheduled = 0;
                                $cart->save($cart);

                                AbandonedCart::$plugin->carts->sendMail(
                                    $cart,
                                    $firstSubject,
                                    $cart->email,
                                    $firstTemplate
                                );
                                continue;
                            }

                            // Second Reminder
                            if ($cart->firstReminder == 1 && $cart->secondReminder == 0) {
                                $totalCarts++;
                                $cart->secondReminder = 1;
                                $cart->isScheduled = 0;
                                $cart->save($cart);

                                AbandonedCart::$plugin->carts->sendMail(
                                    $cart,
                                    $secondSubject,
                                    $cart->email,
                                    $secondTemplate
                                );
                            }
                        }
                    }
                }

                // must be a cron...
                if ($browser == 'Other') {
                    Craft::$app->getQueue()->run();
                    return $this->asJson(Craft::t('app', $totalCarts . ' abandoned carts emails were sent'));
                }

                Craft::$app->getSession()->setNotice(Craft::t('app', $totalCarts . ' abandoned carts emails were sent'));
                return $this->redirect(Craft::$app->getRequest()->referrer);
            }

            // send abandoned carts to queue as per normal
            $abandonedCarts = AbandonedCart::$plugin->carts->getEmailsToSend();

            // must be a cron...
            if ($browser == 'Other') {
                Craft::$app->getQueue()->run();
                return $this->asJson(Craft::t('app', $abandonedCarts . ' abandoned carts were queued'));
            }

            Craft::$app->getSession()->setNotice(Craft::t('app', $abandonedCarts . ' abandoned carts were queued'));
            return $this->redirect(Craft::$app->getRequest()->referrer);
        }

        // throw a 403 error as access is not allowed.
        throw new ForbiddenHttpException('User is not authorized to perform this action');
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

                    $recoveryUrl = AbandonedCart::$plugin->getSettings()->recoveryUrl;
                    if($recoveryUrl) {
                        return $this->redirect($recoveryUrl);
                    }

                    return $this->redirect('shop/cart');
                }
            }

        }

        $session->setNotice("Your cart couldn't be restored, it may have expired.");
        return $this->redirect('shop/cart');
    }

}
