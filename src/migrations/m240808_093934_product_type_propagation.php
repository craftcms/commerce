<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\enums\PropagationMethod;

/**
 * m240808_093934_product_type_propagation migration.
 */
class m240808_093934_product_type_propagation extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // add propagationMethod column to commerce_producttypes table
        $this->addColumn('{{%commerce_producttypes}}', 'propagationMethod', $this->string()->defaultValue(PropagationMethod::All->value)->after('productTitleTranslationKeyFormat'));
        $this->addColumn('{{%commerce_producttypes_sites}}', 'enabledByDefault', $this->boolean()->defaultValue(true)->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240808_093934_product_type_propagation cannot be reverted.\n";
        return false;
    }
}
