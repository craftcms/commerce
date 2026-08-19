<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Validation;

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Order's legacy `defineRules()` was dominated by imperative, side-effecting custom validators
 * (mutating notices, propagating nested model errors under dotted attribute keys like
 * "lineItems.$key", conditional logic based on store settings) that don't map cleanly onto
 * Illuminate's declarative rule closures. Those are kept as plain methods on the `Order` element
 * itself and wired up via {@see \CraftCms\Cms\Validation\Ruleset::after()}, which automatically
 * calls `$order->afterValidate($validator)` when it exists. This ruleset is therefore
 * intentionally minimal, covering only the handful of attributes that were plain type/format
 * rules in the legacy `defineRules()`.
 *
 * @property Order $subject
 */
class OrderRules extends ElementRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['gatewayId'] = ['nullable', 'integer'];
        $rules['shippingAddressId'] = ['nullable', 'integer'];
        $rules['billingAddressId'] = ['nullable', 'integer'];
        $rules['paymentSourceId'] = ['nullable', 'integer'];

        return $rules;
    }
}
