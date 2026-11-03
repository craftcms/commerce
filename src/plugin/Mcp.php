<?php

namespace craft\commerce\plugin;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Transfer;
use craft\commerce\elements\Variant;
use craft\commerce\management\Access;
use craft\commerce\mcp\ConfigurationCapabilities;
use craft\commerce\mcp\ElementData;
use craft\commerce\mcp\elements\OrderAdapter;
use craft\commerce\mcp\elements\ProductAdapter;
use craft\commerce\mcp\elements\TransferAdapter;
use craft\commerce\mcp\elements\VariantAdapter;
use craft\commerce\mcp\InventoryCapabilities;
use craft\commerce\mcp\OrderCapabilities;
use craft\commerce\mcp\ResourceCapabilities;
use craft\commerce\mcp\StoreCapabilities;
use craft\commerce\mcp\TransferCapabilities;
use craft\events\AuthorizationCheckEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\mcp\AdminInstructions;
use craft\mcp\elements\ElementAdapterRegistry;
use craft\mcp\events\CollectingAdminInstructions;
use craft\mcp\serializers\ElementSerializer;
use craft\services\Elements;
use craft\services\Mcp as McpService;
use yii\base\Event;

trait Mcp
{
    private function _registerMcp(): void
    {
        Event::on(McpService::class, McpService::EVENT_REGISTER_CAPABILITY_TYPES, static function(RegisterComponentTypesEvent $event): void {
            array_push($event->types, ResourceCapabilities::class, ConfigurationCapabilities::class, InventoryCapabilities::class, OrderCapabilities::class, TransferCapabilities::class, StoreCapabilities::class);
        });
        Event::on(ElementAdapterRegistry::class, ElementAdapterRegistry::EVENT_REGISTER_ADAPTERS, static function(RegisterComponentTypesEvent $event): void {
            array_push($event->types, ProductAdapter::class, VariantAdapter::class, OrderAdapter::class, TransferAdapter::class);
        });
        Event::on(ElementSerializer::class, ElementSerializer::EVENT_SERIALIZING, [new ElementData(), 'handle']);
        Event::on(AdminInstructions::class, AdminInstructions::EVENT_COLLECT_INSTRUCTIONS, static function(CollectingAdminInstructions $event): void {
            $event->instructions[] = 'Commerce products, variants, orders and transfers use elements.* tools. Read elements.schema before editing. Discover store-management types with commerce.resources.schema and administrator configuration with commerce.configuration.schema. Read current values and deletion effects before writes. Prices use the store currency; refunds use the payment currency. Never retry payment or inventory writes without checking their results. Save transfers as drafts, then dispatch separately.';
        });
        // Generic CMS search, draft and lifecycle tools do not necessarily use our adapters.
        // Apply the same restrictions there, without changing control panel or GraphQL policies.
        foreach ([Elements::EVENT_AUTHORIZE_VIEW => 'view', Elements::EVENT_AUTHORIZE_SAVE => 'save', Elements::EVENT_AUTHORIZE_DELETE => 'delete'] as $eventName => $ability) {
            Event::on(Elements::class, $eventName, static function(AuthorizationCheckEvent $event) use ($ability): void {
                $controller = Craft::$app->controller;
                if (Craft::$app->get('mcp')->tokenScopes === null
                    && !$controller instanceof \craft\controllers\McpController
                    && !$controller instanceof \craft\console\controllers\McpController) {
                    return;
                }
                $element = $event->element;
                if (($element instanceof Product || $element instanceof Variant || $element instanceof Order || $element instanceof Transfer)
                    && !Access::canAccess($event->user, $ability, $element)) {
                    $event->authorized = false;
                }
            });
        }
    }
}
