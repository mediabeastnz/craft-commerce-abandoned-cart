<?php
namespace mediabeastnz\abandonedcart\records;

use craft\db\ActiveRecord;
use craft\commerce\elements\Order;

use yii\db\ActiveQueryInterface;

class AbandonedCart extends ActiveRecord
{

    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%abandonedcart_carts}}';
    }

    public function getOrder(): array
    {
        return Order::findAll($this->orderId);
    }
}
