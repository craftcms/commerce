<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241010_061430_rename_orderable_product_type_type migration.
 */
class m241010_061430_rename_orderable_product_type_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $productTypes = $this->db->createCommand('SELECT id, type FROM {{%commerce_producttypes}}')->queryAll();

        if ($this->db->columnExists('{{%commerce_producttypes}}', 'type')) {
            $this->dropColumn('{{%commerce_producttypes}}', 'type');
        }

        $this->addColumn('{{%commerce_producttypes}}', 'isStructure', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%commerce_producttypes}}', 'maxLevels', $this->smallInteger()->unsigned());

        foreach ($productTypes as $productType) {
            if ($productType['type'] == 'orderable') {
                $this->update('{{%commerce_producttypes}}', ['isStructure' => true, 'maxLevels' => 1], ['id' => $productType['id']]);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241010_061430_rename_orderable_product_type_type cannot be reverted.\n";
        return false;
    }
}
