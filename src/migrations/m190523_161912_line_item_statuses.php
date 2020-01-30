<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190322_161911_register_on_checkout migration.
 */
class m190523_161912_line_item_statuses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'lineItemStatusId')) {
            $this->addColumn('{{%commerce_lineitems}}', 'lineItemStatusId', $this->integer());
        }

        $this->createTable('{{%commerce_lineitemstatuses}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'isArchived' => $this->boolean()->notNull()->defaultValue(false),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%commerce_lineitemstatuses}}', 'isArchived', false);
        $this->createIndex(null, '{{%commerce_lineitems}}', 'lineItemStatusId', false);
        $this->addForeignKey(null, '{{%commerce_lineitems}}', ['lineItemStatusId'], '{{%commerce_lineitemstatuses}}', ['id'], 'SET NULL', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190523_161912_line_item_statuses cannot be reverted.\n";
        return false;
    }
}
