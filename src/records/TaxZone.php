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
 * Tax zone record.
 *
 * @property Country[] $countries
 * @property bool $isCountryBased
 * @property bool $default
 * @property string $description
 * @property string $zipCodeConditionFormula
 * @property int $id
 * @property string $name
 * @property State[] $states
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZone extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TAXZONES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCountries(): ActiveQueryInterface
    {
        return $this->hasMany(Country::class, ['id' => 'countryId'])->viaTable(Table::TAXZONE_COUNTRIES, ['taxZoneId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getStates(): ActiveQueryInterface
    {
        return $this->hasMany(State::class, ['id' => 'stateId'])->viaTable(Table::TAXZONE_STATES, ['taxZoneId' => 'id']);
    }
}
