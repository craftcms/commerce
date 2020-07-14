<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m191008_153947_add_relationship_type_to_discounts migration.
 */
class m191008_153947_add_relationship_type_to_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'categoryRelationshipType';
        $values = ['element', 'sourceElement', 'targetElement'];

        if (!$this->db->columnExists('{{%commerce_discounts}}', $columnName)) {
            $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('element'));
        }
        
        // Set all discounts to source for backward compat
        $data = [
            'categoryRelationshipType' => 'sourceElement',
        ];

        $this->update('{{%commerce_discounts}}', $data);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191008_153947_add_relationship_type_to_discounts cannot be reverted.\n";
        return false;
    }
}
