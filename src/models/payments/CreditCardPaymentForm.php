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
     * @var string|null First name
     */
    public ?string $firstName = null;

    /**
     * @var string|null Last name
     */
    public ?string $lastName = null;

    /**
     * @var string|null Card number
     */
    public ?string $cardNumber = null;

    /**
     * @var string|null Expiry month
     */
    public ?string $month = null;

    /**
     * @var string|null Expiry year
     */
    public ?string $year = null;

    /**
     * @var string|null CVV number
     */
    public ?string $cvv = null;

    /**
     * @var string|null Token
     */
    public ?string $token = null;

    /**
     * @var string|null Expiry date
     */
    public ?string $expiry = null;

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

        $this->cardNumber = preg_replace('/\D/', '', $values['cardNumber'] ?? '');

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
            [['firstName', 'lastName', 'month', 'year', 'cvv', 'cardNumber'], 'required'],
            [['month'], 'integer', 'integerOnly' => true, 'min' => 1, 'max' => 12],
            [['year'], 'integer', 'integerOnly' => true, 'min' => date('Y'), 'max' => (int)date('Y') + 12],
            [['cvv'], 'integer', 'integerOnly' => true],
            [['cvv'], 'string', 'length' => [3, 4]],
            [['cardNumber'], 'integer', 'integerOnly' => true],
            [['cardNumber'], 'string', 'max' => 19],
            [['cardNumber'], 'creditCardLuhn'],
        ];
    }

    /**
     * @param string $attribute
     */
    public function creditCardLuhn(string $attribute): void
    {
        $str = '';
        foreach (array_reverse(str_split($this->$attribute)) as $i => $c) {
            /** @var int $c */
            $str .= ($i % 2) ? $c * 2 : $c;
        }

        if (array_sum(str_split($str)) % 10 !== 0) {
            $this->addError($attribute, Craft::t('commerce', 'Not a valid credit card number.'));
        }
    }
}
