<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\events;

use craft\commerce\elements\Order;
use craft\events\CancelableEvent;
use craft\mail\Message;

class BeforeMailSend extends CancelableEvent
{
    /**
     * @var Order
     */
    public Order $order;

    /**
     * @var Message 
     */
    public Message $message;
}
