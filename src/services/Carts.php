<?php
namespace mediabeastnz\abandonedcart\services;

use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;
use mediabeastnz\abandonedcart\models\AbandonedCart as CartModel;
use mediabeastnz\abandonedcart\jobs\SendEmailReminder;
use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\mail\Message;

use yii\base\Component;

class Carts extends Component
{

    // Public Methods
    // =========================================================================

    public function getEmailsToSend()
    {
        // At the time of this action being triggered, query the database for any
        // orders that meet the criteria e.g. inactive for 1hr.
        // Once the orders have been found we check to see if they've already been
        // added to the abandonedcart_carts table and if so a few additional checks
        // need to happen.

        /*
         * 1. get all orders that meet the criteria
         * 2. check if orders are already in abandonedcart_carts
         * 3. if so - update columns as a max of 2 emails can be sent e.g. 1hr + 24hr
         * 4. if both have been triggered then skip it
         * 5. if not - then add it to the table and set the first column with 1
        */

        $carts = $this->getAbandonedOrders();

        $this->createNewCarts($carts);

        if ($totalScheduled = $this->scheduleReminders()) {
            return $totalScheduled;
        }

        return false;
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

                    Craft::$app->queue->delay(1 * 60)->push(new SendEmailReminder([
                        'cartId' => $cart->id, 
                        'reminder' => 1
                    ]));

                    $cart->isScheduled = 1;
                    $cart->save();

                    $i++;

                // if it's the 2nd time being scheduled then mark as scheduled again
                // and then push it to the queue based on $secondReminderDelay setting
                } elseif ($cart->secondReminder == 0) {

                    Craft::$app->queue->delay(3 * 60)->push(new SendEmailReminder([
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
    public function getAbandonedOrders($start = 'PT1H', $end = 'PT24H')
    {
        $now = new \DateTime();
        $nowInt = new \DateInterval($start);
        $nowInt->invert = 1;
        $now->add($nowInt);
        $now = $now->format('Y-m-d H:i:s');

        $then = new \DateTime();
        $thenInt = new \DateInterval($end);
        $thenInt->invert = 1;
        $then->add($thenInt);
        $then = $then->format('Y-m-d H:i:s');
        
        // Find orders that fit the criteria
        $carts = Order::find();
        $carts->where(['between', 'commerce_orders.dateUpdated', $then, $now]);
        $carts->andWhere('totalPrice > 0');
        $carts->isCompleted(false);
        $carts->email(':notempty:');
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

    public function sendMail($html, $subject, $recipient = null, array $attachments = array()): bool
    {
        $settings = Craft::$app->systemSettings->getSettings('email');
        $message = new Message();

        $message->setFrom([$settings['fromEmail'] => $settings['fromName']]);
        $message->setTo($recipient);
        $message->setSubject($subject);
        $message->setHtmlBody($html);
        if (!empty($attachments) && \is_array($attachments)) {

            foreach ($attachments as $fileId) {
                if ($file = Craft::$app->assets->getAssetById((int)$fileId)) {
                    $message->attach($this->getFolderPath() . '/' . $file->filename, array(
                        'fileName' => $file->title . '.' . $file->getExtension()
                    ));
                }
            }
        }

        return Craft::$app->mailer->send($message);
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