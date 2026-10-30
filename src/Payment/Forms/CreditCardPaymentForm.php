<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Forms;

use function CraftCms\Cms\t;

class CreditCardPaymentForm extends BasePaymentForm
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $number = null;

    public ?string $month = null;

    public ?string $year = null;

    public ?string $cvv = null;

    public ?string $token = null;

    public ?string $expiry = null;

    public bool $threeDSecure = false;

    #[\Override]
    public function setAttributes($values): void
    {
        parent::setAttributes($values);

        $this->number = preg_replace('/\D/', '', $values['number'] ?? '');

        if (isset($values['expiry'])) {
            $expiry = explode('/', (string) $values['expiry']);

            $this->month = trim($expiry[0]);

            if (isset($expiry[1])) {
                $this->year = trim($expiry[1]);
            }
        }
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            'month' => ['required', 'numeric', 'min:1', 'max:12'],
            'year' => ['required', 'numeric', 'min:' . date('Y'), 'max:' . ((int)date('Y') + 12)],
            'cvv' => ['required', 'digits_between:3,4'],
            'number' => ['required', 'digits_between:1,19', function(string $attribute, mixed $value, \Closure $fail) {
                $str = '';
                foreach (array_reverse(str_split((string) $value)) as $i => $c) {
                    $str .= ($i % 2) ? (int)$c * 2 : $c;
                }
                if (array_sum(str_split($str)) % 10 !== 0) {
                    $fail(t('Not a valid credit card number.', category: 'commerce'));
                }
            }],
        ];
    }
}
