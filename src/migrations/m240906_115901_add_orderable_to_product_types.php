<?php

namespace craft\commerce\migrations;

use craft\commerce\models\ProductType;
use craft\db\Migration;
use craft\db\Table;

/**
 * m240906_115901_add_orderable_to_product_types migration.
 */
class m240906_115901_add_orderable_to_product_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'defaultPlacement')) {
            $this->addColumn('{{%commerce_producttypes}}', 'defaultPlacement', $this->enum('defaultPlacement', [
                    ProductType::DEFAULT_PLACEMENT_BEGINNING,
                    ProductType::DEFAULT_PLACEMENT_END, ]
            )->defaultValue('end')->notNull());
        }

        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'type')) {
            $this->addColumn('{{%commerce_producttypes}}', 'type', $this->enum('type', [
                    ProductType::TYPE_CHANNEL,
                    ProductType::TYPE_ORDERABLE, ]
            )->defaultValue('channel')->notNull());
        }

        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'structureId')) {
            $this->addColumn('{{%commerce_producttypes}}', 'structureId', $this->integer());
        }

        $this->createIndex(null, '{{%commerce_producttypes}}', ['structureId'], false);
        $this->addForeignKey(null, '{{%commerce_producttypes}}', ['structureId'], Table::STRUCTURES, ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240906_115901_add_orderable_to_product_types cannot be reverted.\n";
        return false;
    }
}
