<?php

namespace Commerce\Gateways;

/**
 * Payment form model. Used for validation of input, not directly persisted.
 *
 * @property string $firstName
 * @property string $lastName
 * @property int    $month
 * @property int    $year
 * @property int    $cvv
 * @property int    $number
 * @property int    $token
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class PaymentFormModel extends BasePaymentFormModel
{

}