<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * State record.
 *
 * @property int     $id
 * @property string  $name
 * @property string  $abbreviation
 * @property int     $countryId
 * @property Country $country
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class State extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_states}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCountry(): ActiveQueryInterface
    {
        return $this->hasOne(Country::class, ['id' => 'countryId']);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['countryId', 'name', 'abbreviation'], 'required']
        ];
    }
}
