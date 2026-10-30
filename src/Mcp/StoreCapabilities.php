<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresAdmin;
use CraftCms\Cms\Mcp\Attributes\RequiresAdminChanges;
use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Stats\Reports;
use CraftCms\Commerce\Store\StoreManagement;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class StoreCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private StoreManagement $stores, private Reports $reports)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.stores.settings', description: 'Reads a store market, allowed countries and location address ID. Use elements.* address tools for the location address.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageStoreSettings')]
    #[RequiresPermission('commerce-manageGeneralStoreSettings')]
    public function settings(int $storeId): array
    {
        return $this->call(fn() => $this->stores->settings($this->actor->user(), $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.stores.update-settings', description: 'Updates countries (ISO alpha-2 array) and/or marketAddressCondition (condition-builder object). Omitted properties are unchanged.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageStoreSettings')]
    #[RequiresPermission('commerce-manageGeneralStoreSettings')]
    public function updateSettings(int $storeId, #[Schema(type: 'object')] array $attributes): array
    {
        return $this->call(fn() => $this->stores->settings($this->actor->user(), $storeId, $attributes));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.stores.topology', description: 'Reads sites assigned to a store and inventory locations in fulfillment preference order.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresAdmin]
    public function topology(int $storeId): array
    {
        return $this->call(fn() => $this->stores->topology($this->actor->user(), $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.stores.assign-site', description: 'Assigns a site to a store, replacing its previous store assignment.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresAdminChanges]
    public function assignSite(int $storeId, int $siteId): array
    {
        return $this->call(fn() => $this->stores->assignSite($this->actor->user(), $storeId, $siteId));
    }

    /** @param list<int> $ids Complete ordered list of active inventory location IDs.
     * @return array<string, mixed>
     */
    #[McpTool(name: 'commerce.stores.set-inventory-locations', description: 'Replaces a store inventory locations in fulfillment preference order. Read topology before changing.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresAdminChanges]
    public function inventoryLocations(int $storeId, array $ids): array
    {
        return $this->call(fn() => $this->stores->inventoryLocations($this->actor->user(), $storeId, $ids));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.reports.get', description: 'Returns a store sales or customer report. Amounts are in the store currency. Customer reports require customer-management permission.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageOrders')]
    public function report(int $storeId, #[Schema(enum: ['totalOrders', 'totalRevenue', 'averageOrderTotal', 'topProducts', 'topProductTypes', 'topPurchasables', 'totalOrdersByCountry', 'topCustomers', 'newCustomers', 'repeatCustomers'])] string $type, #[Schema(enum: ['all', 'today', 'thisWeek', 'thisMonth', 'thisYear', 'past7Days', 'past30Days', 'past90Days', 'pastYear'])] string $dateRange = 'past30Days', int $limit = 20): array
    {
        return $this->call(fn() => $this->reports->get($this->actor->user(), $storeId, $type, $dateRange, $limit));
    }
}
