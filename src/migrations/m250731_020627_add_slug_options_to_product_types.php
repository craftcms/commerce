<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250731_020627_add_slug_options_to_product_types migration.
 */
class m250731_020627_add_slug_options_to_product_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add showSlugField column
        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'showSlugField')) {
            $this->addColumn(Table::PRODUCTTYPES, 'showSlugField', $this->boolean()->notNull()->defaultValue(true)->after('productTitleTranslationKeyFormat'));
        }

        // Add slugTranslationMethod column
        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'slugTranslationMethod')) {
            $this->addColumn(Table::PRODUCTTYPES, 'slugTranslationMethod', $this->string()->notNull()->defaultValue('site')->after('showSlugField'));
        }

        // Add slugTranslationKeyFormat column
        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'slugTranslationKeyFormat')) {
            $this->addColumn(Table::PRODUCTTYPES, 'slugTranslationKeyFormat', $this->string()->after('slugTranslationMethod'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return true;
    }
}
