<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Currency record.
 *
 * @property int    $id
 * @property string $iso
 * @property bool   $primary
 * @property float  $rate
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.2
 */
class PaymentCurrency extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_paymentcurrencies}}';
    }

    public function rules()
    {
        return [
            [['iso'], 'unique']
        ];
    }

}