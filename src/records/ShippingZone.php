<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping zone record.
 *
 * @property int       $id
 * @property string    $name
 * @property string    $description
 * @property bool      $countryBased
 * @property bool      $default
 *
 * @property Country[] $countries
 * @property State[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingZone extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
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
