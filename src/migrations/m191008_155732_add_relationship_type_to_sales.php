<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191008_155732_add_relationship_type_to_sales migration.
 */
class m191008_155732_add_relationship_type_to_sales extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'categoryRelationshipType';
        $values = ['element', 'sourceElement', 'targetElement'];

        $this->addColumn('{{%commerce_sales}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('element'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191008_155732_add_relationship_type_to_sales cannot be reverted.\n";
        return false;
    }
}
