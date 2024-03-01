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
 * Shipping rule record.
 *
 * @property float $baseRate
 * @property string $description
 * @property bool $enabled
 * @property int $id
 * @property ShippingMethod $method
 * @property int $methodId
 * @property string $orderConditionFormula
 * @property array|string $orderCondition
 * @property float $maxRate

 * @property float $minRate
 * @property string $name
 * @property float $percentageRate
 * @property float $perItemRate
 * @property int $priority
 * @property float $weightRate
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRule extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGRULES;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
        ];
    }

    public function getMethod(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingZone::class, ['id' => 'shippingMethodId']);
    }
}
