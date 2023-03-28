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
 * Store record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $primary
 * @property bool $autoSetNewCartAddresses
 * @property bool $autoSetCartShippingMethodOption
 * @property bool $allowEmptyCartOnCheckout
 * @property bool $allowCheckoutWithoutPayment
 * @property bool $allowPartialPaymentOnCheckout
 * @property bool $requireShippingAddressAtCheckout
 * @property bool $requireBillingAddressAtCheckout
 * @property bool $requireShippingMethodSelectionAtCheckout
 * @property bool $useBillingAddressForTax
 * @property bool $validateBusinessTaxIdAsVatId
 * @property bool $autoSetPaymentSource
 * @property string $orderReferenceFormat
 * @property string $freeOrderPaymentStrategy
 * @property int $sortOrder
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::STORES;
    }
}
