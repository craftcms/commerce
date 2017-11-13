<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Taz zone country
 *
 * @property int                  $taxZoneId
 * @property ActiveQueryInterface $taxZone
 * @property ActiveQueryInterface $country
 * @property int                  $countryId
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
        return '{{%commerce_taxzone_countries}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
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
