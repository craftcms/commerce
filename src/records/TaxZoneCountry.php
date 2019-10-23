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
 * Taz zone country
 *
 * @property ActiveQueryInterface $country
 * @property int $countryId
 * @property int $id
 * @property ActiveQueryInterface $taxZone
 * @property int $taxZoneId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZoneCountry extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TAXZONE_COUNTRIES;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['taxZoneId', 'countryId'], 'unique', 'targetAttribute' => ['taxZoneId', 'countryId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxZone(): ActiveQueryInterface
    {
        return $this->hasOne(TaxZone::class, ['id' => 'taxZoneId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCountry(): ActiveQueryInterface
    {
        return $this->hasOne(Country::class, ['id' => 'countryId']);
    }
}
