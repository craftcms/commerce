<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Customer record.
 *
 * @property int $id The customer's User element ID
 * @property int $primaryBillingAddressId
 * @property int $primaryShippingAddressId
 * @property-read ActiveQueryInterface $primaryShippingAddress
 * @property-read ActiveQueryInterface $primaryBillingAddress
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Customer extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'primaryBillingAddressId',
                    'primaryShippingAddressId',
                    'userId',
                ], 'safe',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CUSTOMERS;
    }

    public function getPrimaryBillingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'primaryBillingAddressId']);
    }

    public function getPrimaryShippingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'primaryShippingAddressId']);
    }
}
