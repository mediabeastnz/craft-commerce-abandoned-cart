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
    public function execute($queue)
    {
        $totalSteps = 10;
        for ($step = 0; $step < $totalSteps; $step++) { 
            return true;
            $this->setProgress($queue, $step / $totalSteps);
            // do something...
        }
        
    }

    protected function defaultDescription()
    {
        return Craft::t('abandoned-cart', 'Sending abandoned cart reminders');
    }
}