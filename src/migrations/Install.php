<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\migrations;

use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        $this->createTables();
        $this->addForeignKeys();
    }

    public function safeDown()
    {
        $this->dropTables();
    }

    public function createTables()
    {
        if (!$this->_tableExists('{{%abandonedcart_carts}}')) {
            $this->createTable('{{%abandonedcart_carts}}', [
                'id' => $this->primaryKey(),
                'orderId' => $this->integer()->notNull(),
                'email' => $this->string()->notNull()->defaultValue(''),
                'clicked' => $this->boolean()->defaultValue(false),
                'isScheduled' => $this->boolean()->defaultValue(false),
                'firstReminder' => $this->boolean()->defaultValue(false),
                'secondReminder' => $this->boolean()->defaultValue(false),
                'isRecovered' => $this->boolean()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid()
            ]);
        }
    }

    public function addForeignKeys()
    {
        if ($this->_tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(null, '{{%abandonedcart_carts}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE', null);
        }
    }

    public function dropTables()
    {
        $this->dropTableIfExists('{{%abandonedcart_carts}}');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns if the table exists.
     *
     * @param string $tableName
     * @param Migration|null $migration
     * @return bool If the table exists.
     */
    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }

}
