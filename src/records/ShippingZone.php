<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;

/**
 * Shipping zone record.
 *
 * @property string[] $countries
 * @property bool $isCountryBased
 * @property string $countryCode
 * @property string $description
 * @property string $zipCodeConditionFormula
 * @property int $id
 * @property string $name
 * @property string[] $administrativeAreas
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZone extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGZONES;
    }
}
