<?php
namespace mediabeastnz\abandonedcart\jobs;

use Craft;
use craft\queue\BaseJob;

use mediabeastnz\abandonedcart\AbandonedCart;

// Usage:
// use mediabeastnz\abandonedcart\jobs\SendEmailReminder;
// Craft::$app->queue->push(new SendEmailReminder());

class SendEmailReminder extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $cartId;

    /**
     * @var int
     */
    public $reminder;

    protected function defaultDescription()
    {
        return Craft::t('abandoned-cart', 'Send abandoned cart reminder');
    }


    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        $totalSteps = 1;
        for ($step = 0; $step < $totalSteps; $step++) { 
              
            $cart = AbandonedCart::$plugin->carts->getAbandonedCartById($this->cartId);
            
            if ($cart) {

                $html = "<html><body><p>Your order stuff goes here...</p></body></html>";
                
                if ($this->reminder == 1) {
                    // do 1st reminder stuff
                    $cart->firstReminder = 1;
                    $cart->isScheduled = 0;
                    $cart->save($cart);

                    AbandonedCart::$plugin->carts->sendMail($html, "Your cart is waiting...#1", $cart->email);

                }

                if ($this->reminder == 2) {
                    // do 2nd reminder stuff
                    $cart->secondReminder = 2;
                    $cart->isScheduled = 0;
                    $cart->save($cart);
                    
                    AbandonedCart::$plugin->carts->sendMail($html, "Your cart is waiting...#2", $cart->email);

                }

            }

            $this->setProgress($queue, $step / $totalSteps);
        }
    }
}