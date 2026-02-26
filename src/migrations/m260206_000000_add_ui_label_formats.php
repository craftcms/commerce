<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m260206_000000_add_ui_label_formats migration.
 */
class m260206_000000_add_ui_label_formats extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'variantUiLabelFormat')) {
            $this->addColumn(
                Table::PRODUCTTYPES,
                'variantUiLabelFormat',
                $this->string()->notNull()->defaultValue('{title}')->after('variantTitleTranslationKeyFormat')
            );
        }

        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'productUiLabelFormat')) {
            $this->addColumn(
                Table::PRODUCTTYPES,
                'productUiLabelFormat',
                $this->string()->notNull()->defaultValue('{title}')->after('productTitleTranslationKeyFormat')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn(Table::PRODUCTTYPES, 'variantUiLabelFormat');
        $this->dropColumn(Table::PRODUCTTYPES, 'productUiLabelFormat');

        return true;
    }
}
