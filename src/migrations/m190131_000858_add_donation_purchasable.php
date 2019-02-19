<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Donation;
use craft\db\Migration;

/**
 * m190131_000858_add_donation_purchasable migration.
 */
class m190131_000858_add_donation_purchasable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('{{%commerce_donations}}', [
            'id' => $this->primaryKey(),
            'sku' => $this->string()->notNull(),
            'availableForPurchase' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, '{{%commerce_donations}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');

        $donation = new Donation();
        $donation->sku = 'DONATION-CC3';
        $donation->availableForPurchase = false;
        Craft::$app->getElements()->saveElement($donation);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190131_000858_add_donation_purchasable cannot be reverted.\n";
        return false;
    }
}
