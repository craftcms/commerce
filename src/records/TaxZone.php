<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax zone record.
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
class TaxZone extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_taxzones}}';
    }

    /**
     * Returns the zone's countries.
     *
     * @return ActiveQueryInterface
     */
    public function getCountries() {
        return $this->hasMany(Country::class, ['id' => 'countryId'])->viaTable('{{%commerce_taxzone_countries}}', ['taxZoneId' => 'id']);
    }

    /**
     * Returns the zone's states
     *
     * @return ActiveQueryInterface
     */
    public function getStates() {
        return $this->hasMany(State::class, ['id' => 'stateId'])->viaTable('{{%commerce_taxzone_states}}', ['taxZoneId' => 'id']);
    }
}