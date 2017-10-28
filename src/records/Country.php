<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Country record.
 *
 * @property int     $id
 * @property string  $name
 * @property string  $iso
 * @property bool    $stateRequired
 * @property State[] $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Country extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_countries}}';
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

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['iso', 'name'], 'required'],
            [['iso'], 'string', 'length' => 2],
        ];
    }
}
