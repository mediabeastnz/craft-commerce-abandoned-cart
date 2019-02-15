<?php
namespace mediabeastnz\abandonedcart\models;

use mediabeastnz\abandonedcart\AbandonedCart as Plugin;
use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;

use craft\base\Model;
use craft\validators\HandleValidator;
use craft\commerce\elements\Order;

class AbandonedCart extends Model
{
    // Public Properties
    // =========================================================================

    public $id;

    public $orderId;

    public $email;

    public $clicked;

    public $isScheduled;

    public $firstReminder;

    public $secondReminder;

    public $isRecovered;

    public $uid;

    public $dateCreated;

    public $dateUpdated;


    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id', 'orderId'], 'number', 'integerOnly' => true],
        ];
    }

    public function getOrder(): array
    {
        return Order::findAll($this->orderId);
    }

    public function getPrettyClicked(): string
    {
        return ($this->clicked) ? "Clicked" : "Not clicked";
    }

    public function getPrettyFirstReminder(): string
    {
        return ($this->firstReminder) ? "Sent" : "Not sent";
    }

    public function getPrettySecondReminder(): string
    {
        return ($this->secondReminder) ? "Sent" : "Not sent";
    }

    public function getIsExpired(): string
    {
        return ($this->secondReminder) ? "Sent" : "Not sent";
    }

    public function getStatus(): string
    {
        if ($this->isScheduled && !$this->isRecovered) {
            return "Scheduled";
        } elseif ($this->isRecovered) {
            return "Recovered";
        } else {
            $expiry = Plugin::$plugin->getSettings()->restoreExpiryHours;
            $expiredTime = $this->dateUpdated;
            $expiredTime->add(new \DateInterval("PT{$expiry}H"));
            $expiredTimestamp = $expiredTime->getTimestamp();

            $now = new \DateTime();
            $nowTimestamp = $now->getTimestamp();

            // if time hasn't expired - yay
            if ($nowTimestamp < $expiredTimestamp) {
                return "Expiring";
            }

            return "Expired";
            
        }
    }
    

    /**
     * Saves a cart.
     *
     * @param AbandonedCart $model The cart to be saved.
     * @param bool $runValidation should we validate this cart before saving.
     * @return bool Whether the cart was saved successfully.
     * @throws Exception if the cart does not exist.
     */
    public function save(AbandonedCart $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = CartRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('app', 'No abandoned cart exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new CartRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Abandoned cart not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->email = $model->email;
        $record->clicked = $model->clicked;
        $record->isScheduled = $model->isScheduled;
        $record->firstReminder = $model->firstReminder;
        $record->secondReminder = $model->secondReminder;
        $record->isRecovered = $model->isRecovered;
    
        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }
}