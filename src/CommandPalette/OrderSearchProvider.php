<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CommandPalette;

use CraftCms\Cms\Cp\CommandPalette\CommandPaletteItem;
use CraftCms\Cms\Cp\CommandPalette\Contracts\SearchProvider;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Plugin;

/**
 * Finds completed orders the user can view. Carts are left out, and orders
 * aren't tied to the site being viewed.
 */
readonly class OrderSearchProvider implements SearchProvider
{
    private const int LIMIT = 10;

    public function __construct(private Plugin $plugin)
    {
    }

    public function getHandle(): string
    {
        return 'elements-order';
    }

    public function getLabel(): string
    {
        return Order::pluralDisplayName();
    }

    public function isAvailable(CraftUser $user): bool
    {
        return $this->plugin->isInstalled && $user->can('commerce-manageOrders');
    }

    public function search(string $query, CraftUser $user): iterable
    {
        $orders = Order::find()
            ->isCompleted()
            ->siteId('*')
            ->unique()
            ->status(null)
            ->search($query)
            ->orderByDesc('score')
            ->limit(self::LIMIT)
            ->get();

        foreach ($orders as $order) {
            $editUrl = $order->getCpEditUrl();

            if ($editUrl === null || !$user->can('view', $order)) {
                continue;
            }

            yield new CommandPaletteItem()
                ->id(sprintf('element-%s', $order->id))
                ->label($order->getUiLabel())
                ->hint($order->email)
                ->description(Order::displayName())
                ->icon('receipt')
                ->href($editUrl)
                ->elementActions($order->actionMenuDescriptors(ElementActionContext::Index));
        }
    }
}
