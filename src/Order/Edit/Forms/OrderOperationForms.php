<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Component\TypeRegistry;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers the forms the order edit screen opens, by the operation type they produce.
 *
 * ```php
 * public function boot(OrderOperationForms $orderOperationForms): void
 * {
 *     $orderOperationForms->register(MyOrderOperationForm::class);
 * }
 * ```
 *
 * @extends TypeRegistry<OrderOperationForm>
 *
 * @since 6.0.0
 */
#[Singleton]
class OrderOperationForms extends TypeRegistry
{
    protected const string CONTRACT = OrderOperationForm::class;

    protected const array DEFAULT_TYPES = [
        AddCustomLineItemForm::class,
        UpdateLineItemForm::class,
        SetLineItemStatusForm::class,
        SetLineItemNotesForm::class,
        SetLineItemOptionsForm::class,
        SetShippingMethodForm::class,
        SetCouponCodeForm::class,
        SetOrderSiteForm::class,
        AddLineItemForm::class,
        SetCustomerForm::class,
        SetAddressForm::class,
        SetFieldValuesForm::class,
    ];

    public function byType(string $type): ?OrderOperationForm
    {
        $class = $this->typeByIdentity($type);

        return $class ? app($class) : null;
    }

    #[\Override]
    protected function identity(string $type): string
    {
        return $type::type();
    }
}
