<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Address record.
 *
 * @property string $address1
 * @property string $address2
 * @property string $alternativePhone
 * @property string $attention
 * @property string $businessId
 * @property string $businessName
 * @property string $businessTaxId
 * @property string $city
 * @property Country $country
 * @property int $countryId
 * @property string $firstName
 * @property int $id
 * @property string $lastName
 * @property string $phone
 * @property State $state
 * @property int $stateId
 * @property string $stateName
 * @property bool $isStoreLocation
 * @property string $title
 * @property string $zipCode
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Address extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_addresses}}';
    }

    /**
     * Returns the address's state
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getState(): ActiveQueryInterface
    {
        return $this->hasOne(State::class, ['id' => 'stateId']);
    }

    /**
     * Returns the address's country
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getCountry(): ActiveQueryInterface
    {
        return $this->hasOne(Country::class, ['id' => 'countryId']);
    }
}
