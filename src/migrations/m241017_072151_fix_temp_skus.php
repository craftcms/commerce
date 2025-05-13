<?php

namespace craft\commerce\migrations;

use craft\commerce\helpers\Purchasable;
use craft\db\Migration;

/**
 * m241017_072151_fix_temp_skus migration.
 */
class m241017_072151_fix_temp_skus extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // get any sku that starts with __temp_ in the purchasables table, loop over them and replace with a new SKU
        $purchasables = (new \craft\db\Query())
            ->select(['id', 'sku'])
            ->from('{{%commerce_purchasables}}')
            ->where(['like', 'sku', '__temp_%', false])
            ->all();

        // Need a unique one per purchasable
        foreach ($purchasables as $purchasable) {
            $newSku = Purchasable::tempSku();
            $this->update('{{%commerce_purchasables}}', ['sku' => $newSku], ['id' => $purchasable['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241017_072151_fix_temp_skus cannot be reverted.\n";
        return false;
    }
}
