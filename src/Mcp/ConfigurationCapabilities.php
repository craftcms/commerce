<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresAdmin;
use CraftCms\Cms\Mcp\Attributes\RequiresAdminChanges;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Management\Resources;
use CraftCms\Commerce\Management\ResourceType;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class ConfigurationCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private Resources $resources)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.schema', description: 'Lists Commerce project-configuration types and operations, or describes a type. Read attributes and deletion effects before writing.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresAdmin]
    public function schema(?ResourceType $type = null): array
    {
        return $this->call(fn() => $this->resources->schema($this->actor->user(), true, $type));
    }

    /** @return array<string, mixed> */
    #[McpResource(uri: 'commerce://configuration', name: 'commerce-configuration', title: 'Commerce configuration', mimeType: 'application/json')]
    #[RequiresAdmin]
    public function resource(): array
    {
        return $this->schema();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.list', description: 'Lists Commerce configuration records without gateway secrets. Supply storeId for store-scoped types.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresAdmin]
    public function list(ResourceType $type, ?int $storeId = null, #[Schema(minimum: 1, maximum: 100)] int $limit = 100, #[Schema(minimum: 0)] int $offset = 0): array
    {
        return $this->call(fn() => $this->resources->list($this->actor->user(), $type, true, $storeId, $limit, $offset));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.get', description: 'Gets Commerce configuration by exactly one supported identifier.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresAdmin]
    public function get(ResourceType $type, #[Schema(type: 'object')] array $identifier, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->get($this->actor->user(), $type, true, $identifier, $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.create', description: 'Creates Commerce project configuration. Read commerce.configuration.schema first.')]
    #[RequiresAdminChanges]
    public function create(ResourceType $type, #[Schema(type: 'object')] array $attributes): array
    {
        return $this->call(fn() => $this->resources->save($this->actor->user(), $type, true, $attributes));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.update', description: 'Updates supplied Commerce configuration attributes. Omitted attributes retain their values.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresAdminChanges]
    public function update(ResourceType $type, #[Schema(type: 'object')] array $identifier, #[Schema(type: 'object')] array $attributes, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->save($this->actor->user(), $type, true, $attributes, $identifier, $storeId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.configuration.delete', description: 'Deletes or archives Commerce configuration. Read deletionEffects in commerce.configuration.schema first.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresAdminChanges]
    public function delete(ResourceType $type, #[Schema(type: 'object')] array $identifier, ?int $storeId = null): array
    {
        return $this->call(fn() => $this->resources->delete($this->actor->user(), $type, true, $identifier, $storeId));
    }
}
