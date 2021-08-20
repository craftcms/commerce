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
     * @var int|null Card number
     */
    public ?int $number = null;

    /**
     * @var int|null Expiry month
     */
    public ?int $month = null;

    /**
     * @var int|null Expiry year
     */
    public ?int $year = null;

    /**
     * @var int|null CVV number
     */
    public ?int $cvv = null;

    /**
     * @var string|null Token
     */
    public ?string $token = null;

    /**
     * @var string|null Expiry date
     */
    public ?string $expiry  = null;

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
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['firstName', 'lastName', 'month', 'year', 'cvv', 'number'], 'required'];
        $rules[] = [['month'], 'integer', 'integerOnly' => true, 'min' => 1, 'max' => 12];
        $rules[] = [['year'], 'integer', 'integerOnly' => true, 'min' => date('Y'), 'max' => date('Y') + 12];
        $rules[] = [['cvv'], 'integer', 'integerOnly' => true];
        $rules[] = [['cvv'], 'string', 'length' => [3, 4]];
        $rules[] = [['number'], 'integer', 'integerOnly' => true];
        $rules[] = [['number'], 'string', 'max' => 19];
        $rules[] = [['number'], 'creditCardLuhn'];

        return $rules;
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
