<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240605_110755_add_title_translations_product_types migration.
 */
class m240605_110755_add_title_translations_product_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_producttypes}}', 'productTitleTranslationKeyFormat', $this->string()->after('productTitleFormat'));
        $this->addColumn('{{%commerce_producttypes}}', 'productTitleTranslationMethod', $this->string()->defaultValue('site')->notNull()->after('productTitleFormat'));

        $this->addColumn('{{%commerce_producttypes}}', 'variantTitleTranslationKeyFormat', $this->string()->after('variantTitleFormat'));
        $this->addColumn('{{%commerce_producttypes}}', 'variantTitleTranslationMethod', $this->string()->defaultValue('site')->notNull()->after('variantTitleFormat'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240605_110755_add_title_translations_product_types cannot be reverted.\n";
        return false;
    }
}
