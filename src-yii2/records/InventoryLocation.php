<?php

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;

/**
 * Location record
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property int $addressId
 *
 */
class InventoryLocation extends ActiveRecord
{
    use SoftDeleteTrait;

    public static function tableName()
    {
        return Table::INVENTORYLOCATIONS;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['handle'], 'unique', 'targetAttribute' => ['handle']],
        ];
    }
}
