<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping zone record.
 *
 * @property Country[] $countries
 * @property bool $countryBased
 * @property string $description
 * @property int $id
 * @property string $name
 * @property State[] $states
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZone extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingzones}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCountries(): ActiveQueryInterface
    {
        return $this->hasMany(Country::class, ['id' => 'countryId'])->viaTable('{{%commerce_shippingzone_countries}}', ['shippingZoneId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getStates(): ActiveQueryInterface
    {
        return $this->hasMany(State::class, ['id' => 'stateId'])->viaTable('{{%commerce_shippingzone_states}}', ['shippingZoneId' => 'id']);
    }
}
