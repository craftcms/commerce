<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Country record.
 *
 * @property int $id
 * @property string $iso
 * @property string $name
 * @property bool $isStateRequired
 * @property State[] $states
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Country extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::COUNTRIES;
    }

    /**
     * Returns the country's states
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getStates(): ActiveQueryInterface
    {
        return $this->hasMany(State::class, ['id' => 'countryId']);
    }
}
