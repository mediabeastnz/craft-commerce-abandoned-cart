<?php
namespace mediabeastnz\abandonedcart\services;

use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;
use mediabeastnz\abandonedcart\models\AbandonedCart as CartModel;
use mediabeastnz\abandonedcart\jobs\SendEmailReminder;
use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\db\Query;
use craft\mail\Message;
use craft\commerce\elements\Order;

use yii\base\Component;

class Carts extends Component
{

    // Public Methods
    // =========================================================================

    public function getEmailsToSend()
    {
        // get abandoned carts
        $carts = $this->getAbandonedOrders();
        // create any new carts
        if($carts->count() > 0) {
            $this->createNewCarts($carts);
        }
        // return carts total
        if ($totalScheduled = $this->scheduleReminders()) {
            return $totalScheduled;
        }
        return 0;
    }

    public function scheduleReminders()
    {
        // get all created abandoned carts that havent been completed
        // Completed being: reminders have already been sent
        $carts = CartRecord::find()->where('isScheduled = 0')->all();

        if ($carts && ($carts) > 0) {
            $i = 0;
            foreach ($carts as $cart) {

                // if it's the 1st time being scheduled then mark as scheduled 
                // and then push it to the queue based on $firstReminderDelay setting
                if ($cart->firstReminder == 0) {

                    Craft::$app->queue->delay(10)->push(new SendEmailReminder([
                        'cartId' => $cart->id, 
                        'reminder' => 1
                    ]));

                    $cart->isScheduled = 1;
                    $cart->save();

                    $i++;

                // if it's the 2nd time being scheduled then mark as scheduled again
                // and then push it to the queue based on $secondReminderDelay setting
                } elseif ($cart->secondReminder == 0) {

                    Craft::$app->queue->delay(10)->push(new SendEmailReminder([
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
        if ($orders && $orders->count() > 0) {
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
    // Note: Commerce::purgeInactiveCartsDuration() may come into play here.
    public function getAbandonedOrders($start = 'PT3H', $end = 'PT24H')
    {
        $now = new \DateTime();
        $nowInt = new \DateInterval($start);
        $nowInt->invert = 0;
        $now->add($nowInt);
        $now = $now->format('Y-m-d H:i:s');
        
        $then = new \DateTime();
        $thenInt = new \DateInterval($end);
        $thenInt->invert = 0;
        $then->add($thenInt);
        $then = $then->format('Y-m-d H:i:s');
        
        // Find orders that fit the criteria
        $carts = Order::find();
        $carts->where(['between', 'commerce_orders.dateUpdated', $now, $then]);
        $carts->andWhere('totalPrice > 0');
        $carts->andWhere('isCompleted = 0');
        $carts->andWhere('email IS NOT NULL');
        $carts->orderBy('commerce_orders.dateUpdated desc');
        $carts->all();

        return $carts;
    }

    public function getAbandonedCarts()
    {

        $rows = $this->_createAbandonedCartsQuery()
            ->all();

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
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        // $oldTemplatesPath = $view->getTemplatesPath();
        // $view->setTemplatesPath(AbandonedCart::$plugin->getInstance()->getBasePath());
        // $html = $view->renderTemplate('/emails');
        // $view->setTemplatesPath($oldTemplatesPath);

        // get the order from the cart
        $order = Order::findOne($cart->orderId);

        if(!$order) {
            $error = Craft::t('app', 'Could not find Order for Abandoned Cart email.');
            Craft::error($error, __METHOD__);
            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);
            return false;
        }

        // template variables
        $renderVariables = [
            'order' => $order,
            'discount' => false, // feature coming soon ;)
            'checkoutLink' => 'abandoned-cart-restore?number=' . $order->number
        ];

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
        $settings = Craft::$app->systemSettings->getSettings('email');
        
        // build the email
        $newEmail = new Message();
        $newEmail->setFrom([$settings['fromEmail'] => $settings['fromName']]);
        $newEmail->setTo($recipient);
        $newEmail->setSubject($subject);
        $newEmail->setHtmlBody($emailBody);

        // attempt to send
        try {
            if (!Craft::$app->getMailer()->send($newEmail)) {
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

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving Abandoned Carts.
     *
     * @return Query The query object.
     */
    private function _createAbandonedCartsQuery(): Query
    {
        return (new Query())
            ->select('*')
            ->from(['{{%abandonedcart_carts}}'])
            ->orderBy('dateUpdated desc');
    }

}