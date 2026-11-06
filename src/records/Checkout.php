<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Checkout record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $enabled
 * @property bool $isDefault
 * @property int|null $sortOrder
 * @property string|null $brandColor
 * @property string $colorScheme
 * @property string $cornerStyle
 * @property string|null $logoUrl
 * @property string|null $returnUrl
 * @property string|null $continueShoppingUrl
 * @property bool $allowPartialPayment
 * @property string|null $partialPaymentPercentages
 * @property bool|null $requireBillingAddress
 * @property string|null $orderCondition
 * @property string|null $orderConditionMessage
 * @property string $uid
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Checkout extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CHECKOUTS;
    }
}
