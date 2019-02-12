<?php
namespace mediabeastnz\abandonedcart\services;

use mediabeastnz\abandonedcart\records\AbandonedCart as cartRecord;

use craft\commerce\elements\Order;

use yii\base\Component;

class Carts extends Component
{

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

        $carts = $this->getAbandonedCarts();
        $this->createNewCarts($carts);
        if ($totalSent = $this->sendCartReminders()) {
            return $totalSent;
        }
        return false;
    }

    public function sendCartReminders()
    {
        $carts = cartRecord::find()->all();
        if ($carts && count($carts) > 0) {
            $i = 0;
            foreach ($carts as $cart) {
                if ($cart->firstReminder == 0) {
                    // get the email
                    // get the template for the firstReminder
                    // configure the email
                    // send it?? Or push to queue??
                    // if sending it here update $cart->firstReminder to 1
                    $cart->firstReminder = true;
                    $cart->save();
                    $i++;
                } elseif ($cart->secondReminder == 0) {
                    $cart->firstReminder = true;
                    $cart->secondReminder = true;
                    $cart->save();
                    $i++;
                } else {
                    // both are set so skip?
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
                $existingCart = cartRecord::find()->where(['orderID' => $order->id])->one();
                if (!$existingCart) {
                    $newCart = new cartRecord();
                    $newCart->orderId = $order->id;
                    $newCart->email = $order->email;
                    $newCart->save();
                }
            }
            return true;
        }
        return false;
    }
    
    public function getAbandonedCarts()
    {
        $edge = new \DateTime();
        $interval = new \DateInterval('PT1H');
        $interval->invert = 1;
        $edge->add($interval);
        $edge = $edge->format(\DateTime::ATOM);

        $edge2 = new \DateTime();
        $interval2 = new \DateInterval('PT24H');
        $interval2->invert = 1;
        $edge2->add($interval2);
        $edge2 = $edge2->format(\DateTime::ATOM);

        $carts = Order::find();
        $carts->dateUpdated(['and', '>='.$edge2, '<='.$edge]);
        $carts->isCompleted(false);
        $carts->email(':notempty:');
        $carts->where('totalPrice > 0');
        $carts->orderBy('elements.dateCreated desc');
        $carts->all();

        return $carts;
    }
}