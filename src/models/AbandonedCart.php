<?php
namespace mediabeastnz\abandonedcart\models;

use craft\base\Model;
use craft\validators\HandleValidator;

class AbandonedCart extends Model
{
    // Public Properties
    // =========================================================================

    public $id;

    public $orderId;

    public $email;

    public $firstReminder;

    public $secondReminder;


    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id', 'orderId'], 'number', 'integerOnly' => true],
        ];
    }

}