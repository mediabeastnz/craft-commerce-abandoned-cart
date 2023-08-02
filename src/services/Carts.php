<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\services;

use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;
use mediabeastnz\abandonedcart\models\AbandonedCart as CartModel;
use mediabeastnz\abandonedcart\jobs\SendEmailReminder;
use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\db\Query;
use craft\mail\Message;
use craft\commerce\elements\Order;
use DateInterval;
use DateTime;
use DateTimeZone;
use yii\base\Component;

class Carts extends Component
{

    // Public Methods
    // =========================================================================

    public function getEmailsToSend()
    {
        $testMode = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->testMode);
        // get abandoned carts
        $carts = $this->getAbandonedOrders();
        // create any new carts
        if($carts->count() > 0) {
            $this->createNewCarts($carts);
        }

        // return carts total
        if (!$testMode) {
            if ($totalScheduled = $this->scheduleReminders()) {
                return $totalScheduled;
            }
            return 0;
        }
        return true;
    }

    public function scheduleReminders()
    {
        // get all created abandoned carts that havent been completed
        // Completed being: reminders have already been sent
        $carts = CartRecord::find()->where('isScheduled = 0')->all();

        $firstDelay = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->firstReminderDelay);
        $secondDelay = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->secondReminderDelay);

        $firstDelayInSeconds = $firstDelay * 3600;
        $secondDelayInSeconds = $secondDelay * 3600;

        $secondReminderDisabled = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->disableSecondReminder);

        if ($carts && ($carts) > 0) {
            $i = 0;
            foreach ($carts as $cart) {

                // if it's the 1st time being scheduled then mark as scheduled
                // and then push it to the queue based on $firstReminderDelay setting
                if ($cart->firstReminder == 0) {

                    Craft::$app->queue->delay($firstDelayInSeconds)->push(new SendEmailReminder([
                        'cartId' => $cart->id,
                        'reminder' => 1
                    ]));

                    $cart->isScheduled = 1;
                    $cart->save();

                    $i++;

                // if it's the 2nd time being scheduled then mark as scheduled again
                // and then push it to the queue based on $secondReminderDelay setting
                // this wont get triggered if 2nd is disabled via settings
                } elseif ($cart->secondReminder == 0 && !$secondReminderDisabled) {

                    Craft::$app->queue->delay($secondDelayInSeconds)->push(new SendEmailReminder([
                        'cartId' => $cart->id,
                        'reminder' => 2
                    ]));

                    $cart->isScheduled = 1;
                    $cart->save();

                    $i++;

                } else {
                    // ideally finished carts will be marked as completed/failed and
                    // no futher emails will be queued.
                }
            }
            return $i;
        }
        return false;
    }

    public function createNewCarts(\craft\commerce\elements\db\OrderQuery $orders)
    {
        $orders = $orders->all();
        if ($orders && count($orders) > 0) {
            foreach ($orders as $order) {
                // check for existing cart first
                // if none exist - create a new record
                $existingCart = CartRecord::find()->where(['orderID' => $order->id])->one();
                if (!$existingCart) {
                    $newCart = new CartRecord();
                    $newCart->orderId = $order->id;
                    $newCart->email = $order->email;
                    $newCart->save();
                }
            }
            return true;
        }
        return false;
    }

    // Get all abandoned commerce orders that have been inactive for more than 1hr
    // But no further back than 12 hours
    // Note: Commerce::purgeInactiveCartsDuration() may come into play here.
    public function getAbandonedOrders($start = '1', $end = '12')
    {

        $blacklist = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->blacklist);
        if (!empty($blacklist)) {
            $blacklist = explode(',',$blacklist);
        }

        // Find orders that fit the criteria
        $UTC = new DateTimeZone("UTC");
        $dateUpdatedStart = new DateTime();
        $dateUpdatedStart->setTimezone($UTC);
        $dateUpdatedStart->sub(new DateInterval('PT'.$start.'H'));

        $dateUpdatedEnd = new DateTime();
        $dateUpdatedEnd->setTimezone($UTC);
        $dateUpdatedEnd->sub(new DateInterval('PT'.$end.'H'));

        $carts = Order::find();
        $carts->where(['<=', 'commerce_orders.dateUpdated', $dateUpdatedStart->format('Y-m-d H:i:s')]);
        $carts->andWhere(['>=', 'commerce_orders.dateUpdated', $dateUpdatedEnd->format('Y-m-d H:i:s')]);
        $carts->andWhere('totalPrice > 0');
        $carts->andWhere('isCompleted = 0');
        $carts->andWhere('email != ""');
        if (is_array($blacklist)) {
            $carts->andWhere(['not in', 'email', $blacklist]);
        }
        $carts->orderBy('commerce_orders.dateUpdated desc');
        $carts->all();
        return $carts;
    }

    // TODO: make query more effcient e.g. sub query to get order and customer details
    public function getAbandonedCarts($limit = null)
    {

        if ($limit) {
            $rows = $this->_createAbandonedCartsQuery()->limit($limit)->all();
        } else {
            $rows = $this->_createAbandonedCartsQuery()->all();
        }

        $carts = [];

        foreach ($rows as $row) {
            $carts[] = new CartModel($row);
        }

        return $carts;
    }

    public function getAbandonedCartById(int $id)
    {
        $row = $this->_createAbandonedCartsQuery()
            ->where(['id' => $id])
            ->one();

        return $row ? new CartModel($row) : null;
    }

    public function getAbandonedCartsTotal()
    {
        return $this->_createAbandonedCartsQuery()->count();
    }


    public function getAbandonedCartsRecovered()
    {
        $ids = $this->_createAbandonedCartsQuery()
            ->select('orderId')
            ->where(['isRecovered' => 1])
            ->column();
        if($ids) {
            $orders = Order::find()
                ->where(['commerce_orders.id' => $ids])
                ->select('SUM(totalPrice) as total')
                ->column();
            return $orders[0];
        }
        return false;
    }

    public function getAbandonedCartsRecoveredThisMonth()
    {
        $ids = $this->_createAbandonedCartsQuery()
            ->select('orderId')
            ->where(['isRecovered' => 1])
            ->andWhere('MONTH(dateUpdated) = MONTH(CURDATE())')
            ->column();
        if($ids) {
            $orders = Order::find()
                ->where(['commerce_orders.id' => $ids])
                ->select('SUM(totalPrice) as total')
                ->column();
            return $orders[0];
        }
        return false;
    }

    public function getAbandonedCartsRecoveredCount()
    {
        $ids = $this->_createAbandonedCartsQuery()
            ->select('orderId')
            ->where(['isRecovered' => 1])
            ->column();
        return count($ids);
        return false;
    }

    public function getAbandondedCartsConversion()
    {
        $recovered = $this->_createAbandonedCartsQuery()->where('isRecovered = 1')->count();
        $total = $this->getAbandonedCartsTotal();
        if ($total > 0 && $recovered > 0) {
            $percent = ($recovered / $total) * 100;
            return $percent;
        }
        return 0;
    }

    public function getAbandonedCartByOrderId(int $id)
    {
        $row = $this->_createAbandonedCartsQuery()
            ->where(['orderId' => $id])
            ->one();

        return $row ? new CartModel($row) : null;
    }

    /**
     * Send the abandoned cart reminder email.
     *
     * @param AbandonedCart $cart
     * @return bool $result
     */
    public function sendMail($cart, $subject, $recipient = null, $templatePath = null): bool
    {
        // settings/defaults
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $originalLanguage = Craft::$app->language;

        if (strpos($templatePath, "abandoned-cart/emails") !== false) {
            $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        } else {
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        }

        // get the order from the cart
        $order = Order::findOne($cart->orderId);

        if (!$order) {
            $error = Craft::t('app', 'Could not find Order for Abandoned Cart email.');
            Craft::error($error, __METHOD__);
            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);
            return false;
        }

        if (!$order->hasLineItems()) {
            $warning = Craft::t('app', 'Skipped Abandoned Cart email, Order doesn\'t have Line Items.');
            Craft::warning($warning, __METHOD__);
            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);
            return false;
        }
        
        // use order language
        Craft::$app->language = $order->orderLanguage;

        $checkoutLink = 'abandoned-cart-restore?number=' . $order->number;

        $discount = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->discountCode);
        if ($discount) {
            $discountCode = $discount;
            $checkoutLink = $checkoutLink . '&couponCode=' . $discountCode;
        } else {
            $discountCode = false;
        }

        // template variables
        $renderVariables = [
            'order' => $order,
            'discount' => $discountCode,
            'currentSite' => $order->orderSite,
            'checkoutLink' => $checkoutLink
        ];

        $subject = $view->renderString($subject, $renderVariables);
        $templatePath = $view->renderString($templatePath, $renderVariables);

        // validate that the email template exists
        if (!$view->doesTemplateExist($templatePath)) {
            $error = Craft::t('app', 'Email template does not exist at “{templatePath}”.', [
                'templatePath' => $templatePath,
            ]);
            Craft::error($error, __METHOD__);
            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);
            return false;
        }

        // set the template as the email body
        $emailBody = $view->renderTemplate($templatePath, $renderVariables);

        // Get from address from site settings
        $settings = Craft::$app->projectConfig->get('email');

        // build the email
        $newEmail = Craft::$app->getMailer()->compose();
        $newEmail->setFrom([Craft::parseEnv($settings['fromEmail']) => Craft::parseEnv($settings['fromName'])]);
        $newEmail->setTo($recipient);
        $newEmail->setSubject($subject);
        $newEmail->setHtmlBody($emailBody);

        // attempt to send
        try {
            if (!$newEmail->send()) {
                $error = Craft::t('app', 'Abandoned cart email “{email}” could not be sent for order “{order}”.', [
                    'order' => $order->id
                ]);

                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        } catch (\Exception $e) {
            $error = Craft::t('commerce', 'Abandoned cart email could not be sent for order “{order}”. Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'order' => $order->id
            ]);

            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        return true;
    }

    public function markCartAsRecovered(Order $order)
    {
        $cart = $this->getAbandonedCartByOrderId($order->id);
        if ($cart) {
            $cart->isRecovered = true;
            $cart->save($cart);
        }
    }

    // Static Methods
    // =========================================================================

    // Shoutout to @CraftCMS and the FeedMe plugin for this helper function
    public static function getBrowserName($userAgent)
    {
        if (strpos($userAgent, 'Opera') || strpos($userAgent, 'OPR/')) {
            return 'Opera';
        } else if (strpos($userAgent, 'Edge')) {
            return 'Edge';
        } else if (strpos($userAgent, 'Chrome')) {
            return 'Chrome';
        } else if (strpos($userAgent, 'Safari')) {
            return 'Safari';
        } else if (strpos($userAgent, 'Firefox')) {
            return 'Firefox';
        } else if (strpos($userAgent, 'MSIE') || strpos($userAgent, 'Trident/7')) {
            return 'Internet Explorer';
        }
        return 'Other';
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving Abandoned Carts.
     *
     * @return Query The query object.
     */
    private function _createAbandonedCartsQuery(): Query
    {
        // don't get carts the might be sent to blacklisted
        $blacklist = Craft::parseEnv(AbandonedCart::$plugin->getSettings()->blacklist);
        if (!empty($blacklist)) {
            $blacklist = explode(',', $blacklist);
        } else {
            $blacklist = [];
        }

        return (new Query())
            ->select('*')
            ->from(['{{%abandonedcart_carts}}'])
            ->where(['not in', 'email', $blacklist])
            ->orderBy('dateUpdated desc');
    }

}
