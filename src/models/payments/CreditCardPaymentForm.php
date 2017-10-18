<?php

namespace craft\commerce\models\payments;

use Craft;
use Omnipay\Common\Helper as OmnipayHelper;

/**
 * Base Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.1
 */
class CreditCardPaymentForm extends BasePaymentForm
{
    // Properties
    // =========================================================================

    /**
     * @var string First name
     */
    public $firstName;

    /**
     * @var string Last name
     */
    public $lastName;

    /**
     * @var int Card number
     */
    public $number;

    /**
     * @var int Expiry month
     */
    public $month;

    /**
     * @var int Expiry year
     */
    public $year;

    /**
     * @var int CVV number
     */
    public $cvv;

    /**
     * @var string Token
     */
    public $token;

    /**
     * @var string Expiry date
     */
    public $expiry;

    /**
     * @var bool
     */
    public $threeDSecure = false;

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        parent::setAttributes($values, $safeOnly);

        $this->number = preg_replace('/\D/', '', $values['number'] ?? '');

        if (isset($values['expiry'])) {
            $expiry = explode('/', $values['expiry']);

            if (isset($expiry[0])) {
                $this->month = trim($expiry[0]);
            }

            if (isset($expiry[1])) {
                $this->year = trim($expiry[1]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['firstName', 'lastName', 'month', 'year', 'cvv', 'number'], 'required'],
            [['month'], 'integer', 'integerOnly' => true, 'min' => 1, 'max' => 12],
            [['year'], 'integer', 'integerOnly' => true, 'min' => date('Y'), 'max' => date('Y') + 12],
            [['cvv'], 'integer', 'integerOnly' => true],
            [['cvv'], 'string', 'length' => [3, 4]],
            [['number'], 'integer', 'integerOnly' => true],
            [['number'], 'string', 'max' => 19],
            [['number'], 'creditCardLuhn']
        ];
    }

    /**
     * @param $attribute
     * @param $params
     */
    public function creditCardLuhn($attribute, $params)
    {
        if (!OmnipayHelper::validateLuhn($this->$attribute)) {
            $this->addError($attribute, Craft::t('commerce', 'Not a valid Credit Card Number'));
        }
    }
}
