<?php

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Transfer record
 * @property int $id
 * @property string $transferStatus
 * @property ?int $originLocationId
 * @property ?int $destinationLocationId
 */
class Transfer extends ActiveRecord
{
    public static function tableName()
    {
        return Table::TRANSFERS;
    }
}
