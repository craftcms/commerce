<?php

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

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[['.$columnName.']] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_sales}} drop constraint {{%commerce_sales_".$columnName."_check}}, add check ({$check})");
        } else {
            $this->addColumn('{{%commerce_sales}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('element'));
        }
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
