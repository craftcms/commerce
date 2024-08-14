<?php

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Transfer record
 * @property int $id
 * @property int $transferId
 * @property int $inventoryItemId
 * @property string $inventoryItemDescription
 * @property int $quantity
 * @property int $quantityAccepted
 * @property int $quantityRejected
 */
class TransferDetail extends ActiveRecord
{
    public static function tableName()
    {
        return Table::TRANSFERDETAILS;
    }
}
