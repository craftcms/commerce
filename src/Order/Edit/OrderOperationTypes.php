<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Order\Edit\Operations\AddAdjustment;
use CraftCms\Commerce\Order\Edit\Operations\AddCustomLineItem;
use CraftCms\Commerce\Order\Edit\Operations\AddLineItem;
use CraftCms\Commerce\Order\Edit\Operations\ClearNotices;
use CraftCms\Commerce\Order\Edit\Operations\CopyAddress;
use CraftCms\Commerce\Order\Edit\Operations\Recalculate;
use CraftCms\Commerce\Order\Edit\Operations\RecalculateCharges;
use CraftCms\Commerce\Order\Edit\Operations\RemoveAddress;
use CraftCms\Commerce\Order\Edit\Operations\RemoveAdjustment;
use CraftCms\Commerce\Order\Edit\Operations\RemoveLineItem;
use CraftCms\Commerce\Order\Edit\Operations\SetAddress;
use CraftCms\Commerce\Order\Edit\Operations\SetCouponCode;
use CraftCms\Commerce\Order\Edit\Operations\SetCustomer;
use CraftCms\Commerce\Order\Edit\Operations\SetDateOrdered;
use CraftCms\Commerce\Order\Edit\Operations\SetFieldValues;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemNotes;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemOptions;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemStatus;
use CraftCms\Commerce\Order\Edit\Operations\SetOrderSite;
use CraftCms\Commerce\Order\Edit\Operations\SetReference;
use CraftCms\Commerce\Order\Edit\Operations\SetShippingMethod;
use CraftCms\Commerce\Order\Edit\Operations\UpdateAdjustment;
use CraftCms\Commerce\Order\Edit\Operations\UpdateLineItem;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers the operations the order edit screen can make, by their `type()`.
 *
 * ```php
 * public function boot(OrderOperationTypes $orderOperationTypes): void
 * {
 *     $orderOperationTypes->register(MyOrderOperation::class);
 * }
 * ```
 *
 * @extends TypeRegistry<OrderOperation>
 *
 * @since 6.0.0
 */
#[Singleton]
class OrderOperationTypes extends TypeRegistry
{
    protected const ?string CONTRACT = OrderOperation::class;

    protected const array DEFAULT_TYPES = [
        AddLineItem::class,
        AddCustomLineItem::class,
        UpdateLineItem::class,
        RemoveLineItem::class,
        SetLineItemStatus::class,
        SetLineItemNotes::class,
        SetLineItemOptions::class,
        AddAdjustment::class,
        UpdateAdjustment::class,
        RemoveAdjustment::class,
        SetCustomer::class,
        SetAddress::class,
        CopyAddress::class,
        RemoveAddress::class,
        SetShippingMethod::class,
        SetCouponCode::class,
        SetReference::class,
        SetOrderSite::class,
        SetDateOrdered::class,
        ClearNotices::class,
        SetFieldValues::class,
        Recalculate::class,
        RecalculateCharges::class,
    ];

    /** @return class-string<OrderOperation>|null */
    public function byType(string $type): ?string
    {
        return $this->typeByIdentity($type);
    }

    #[\Override]
    protected function identity(string $type): string
    {
        return $type::type();
    }
}
