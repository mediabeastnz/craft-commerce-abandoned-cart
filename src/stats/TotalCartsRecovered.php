<?php
namespace mediabeastnz\abandonedcart\stats;

use mediabeastnz\abandonedcart\AbandonedCart;
use mediabeastnz\abandonedcart\records\AbandonedCart as CartRecord;

use craft\commerce\base\Stat;
use yii\db\Expression;

/**
 * Total Carts Recovered
 */
class TotalCartsRecovered extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'totalCartsRecovered';

    /**
     * @inheritdoc
     */
    public $cache = false;

    /**
     * @inheritDoc
     */
    public function getData()
    {
        // get all orders in date range
        $query = $this->_createStatQuery();
        $query->select('id');
        $ids = $query->column();

        // get all recovered carts based on ids from above
        $cartIds = CartRecord::find()
        ->select('orderId')
        ->where(['isRecovered' => 1])
        ->andWhere(['orderId' => $ids])
        ->column();

        // get all orders that are recovered
        $query2 = $this->_createStatQuery();
        $query2->select('SUM(totalPrice) as total');
        $query2->andWhere(['id' => $cartIds]);
        $total = $query2->scalar();

        return $total;
    }
}