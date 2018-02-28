<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m170831_130000_paymentCurreny_primary_not_null
 */
class m170831_130000_paymentCurreny_primary_not_null extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $primaryId = (new Query())
            ->select(['id'])
            ->from('{{%commerce_paymentcurrencies}}')
            ->where(['primary' => true])
            ->scalar();

        $this->update('{{%commerce_paymentcurrencies}}', ['primary' => false]);
        $this->update('{{%commerce_paymentcurrencies}}', ['primary' => true], ['id' => $primaryId]);

        $this->alterColumn('{{%commerce_paymentcurrencies}}', 'primary', $this->boolean()->notNull()->defaultValue(false));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170831_130000_paymentCurreny_primary_not_null cannot be reverted.\n";

        return false;
    }
}
