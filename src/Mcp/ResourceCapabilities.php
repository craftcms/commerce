<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Management\Resources;
use CraftCms\Commerce\Management\ResourceType;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class ResourceCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private Resources $resources)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.schema', description: 'Lists Commerce store-management types and allowed operations, or describes identifiers, attributes and deletion effects for one type. Call before writes.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('accessPlugin-commerce')]
    public function schema(?ResourceType $type = null): array
    {
        return $this->call(fn() => $this->resources->schema($this->actor->user(), false, $type));
    }

    /** @return array<string, mixed> */
    #[McpResource(uri: 'commerce://resources', name: 'commerce-resources', title: 'Commerce store-management resources', mimeType: 'application/json')]
    #[RequiresPermission('accessPlugin-commerce')]
    public function resource(): array
    {
        return $this->schema();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.list', description: 'Lists Commerce store-management records. Supply storeId for store-scoped types. Continue with nextOffset.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('accessPlugin-commerce')]
    public function list(ResourceType $type, ?int $storeId = null, #[Schema(minimum: 1, maximum: 100)] int $limit = 100, #[Schema(minimum: 0)] int $offset = 0): array
    {
        return $this->call(fn() => $this->resources->list($this->actor->user(), $type, false, $storeId, $limit, $offset));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.get', description: 'Gets one record. identifier must contain exactly one supported lookup key from commerce.resources.schema.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('accessPlugin-commerce')]
    public function get(ResourceType $type, #[Schema(type: 'object')] array $identifier, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->get($this->actor->user(), $type, false, $identifier, $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.create', description: 'Creates a Commerce record. Read commerce.resources.schema first. Store-scoped types require attributes.storeId.')]
    #[RequiresPermission('accessPlugin-commerce')]
    public function create(ResourceType $type, #[Schema(type: 'object')] array $attributes): array
    {
        return $this->call(fn() => $this->resources->save($this->actor->user(), $type, false, $attributes));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.update', description: 'Updates supplied attributes only. Read current values and commerce.resources.schema first. Records cannot move between stores.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('accessPlugin-commerce')]
    public function update(ResourceType $type, #[Schema(type: 'object')] array $identifier, #[Schema(type: 'object')] array $attributes, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->save($this->actor->user(), $type, false, $attributes, $identifier, $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.resources.delete', description: 'Deletes a Commerce record. Read deletionEffects in commerce.resources.schema before calling.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('accessPlugin-commerce')]
    public function delete(ResourceType $type, #[Schema(type: 'object')] array $identifier, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->delete($this->actor->user(), $type, false, $identifier, $storeId));
    }
}
