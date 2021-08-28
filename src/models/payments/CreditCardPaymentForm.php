<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\payments;

use Craft;

/**
 * Credit Card Payment form model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CreditCardPaymentForm extends BasePaymentForm
{
    /**
     * @var string First name
     */
    public string $firstName;

    /**
     * @var string Last name
     */
    public string $lastName;

    /**
     * @var int Card number
     */
    public int $number;

    /**
     * @var int Expiry month
     */
    public int $month;

    /**
     * @var int Expiry year
     */
    public int $year;

    /**
     * @var int CVV number
     */
    public int $cvv;

    /**
     * @var string Token
     */
    public string $token;

    /**
     * @var string Expiry date
     */
    public string $expiry;

    /**
     * @var bool
     */
    public bool $threeDSecure = false;


    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
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
    protected function defineRules(): array
    {
        return [
            [['firstName', 'lastName', 'month', 'year', 'cvv', 'number'], 'required'],
            [['month'], 'integer', 'integerOnly' => true, 'min' => 1, 'max' => 12],
            [['year'], 'integer', 'integerOnly' => true, 'min' => date('Y'), 'max' => date('Y') + 12],
            [['cvv'], 'integer', 'integerOnly' => true],
            [['cvv'], 'string', 'length' => [3, 4]],
            [['number'], 'integer', 'integerOnly' => true],
            [['number'], 'string', 'max' => 19],
            [['number'], 'creditCardLuhn'],
        ];
    }

    /**
     * @param string $attribute
     * @param $params
     */
    public function creditCardLuhn(string $attribute, $params): void
    {
        $str = '';
        foreach (array_reverse(str_split($this->$attribute)) as $i => $c) {
            $str .= ($i % 2) ? $c * 2 : $c;
        }

        if (array_sum(str_split($str)) % 10 !== 0) {
            $this->addError($attribute, Craft::t('commerce', 'Not a valid credit card number.'));
        }
    }
}
