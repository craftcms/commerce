<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m250120_080035_move_to_tax_id_validators migration.
 */
class m250120_080035_move_to_tax_id_validators extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_taxrates}}', 'taxIdValidators', $this->text()->after('isVat'));

        $taxRates = (new \craft\db\Query())
            ->select(['id', 'isVat'])
            ->from(['{{%commerce_taxrates}}'])
            ->all();

        foreach ($taxRates as $taxRate) {
            $taxIdValidators = $taxRate['isVat'] ? ['craft\commerce\taxidvalidators\EuVatIdValidator'] : [];
            $this->update('{{%commerce_taxrates}}', ['taxIdValidators' => json_encode($taxIdValidators)], ['id' => $taxRate['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250120_080035_move_to_tax_id_validators cannot be reverted.\n";
        return false;
    }
}
