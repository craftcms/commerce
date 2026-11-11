<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Fields;

use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Gql\Gql as GqlService;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Gql\DigitalProductArguments;
use CraftCms\Commerce\DigitalProduct\Gql\DigitalProductInterface;
use CraftCms\Commerce\DigitalProduct\Gql\DigitalProductResolver;
use GraphQL\Type\Definition\Type;
use Override;

use function CraftCms\Cms\t;

/**
 * Relates digital products. Replaces the Digital Products plugin’s Products field.
 */
class DigitalProducts extends BaseRelationField
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Older Digital Products fields may still carry this setting.
        unset($config['targetLocale']);
        parent::__construct($config);
    }

    #[Override]
    public static function icon(): string
    {
        return 'file-arrow-down';
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Digital Products', category: 'commerce');
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a digital product', category: 'commerce');
    }

    #[Override]
    public function getContentGqlType(): array|Type
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(DigitalProductInterface::getType()),
            'args' => DigitalProductArguments::getArguments(),
            'resolve' => DigitalProductResolver::class . '::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }

    public static function elementType(): string
    {
        return DigitalProduct::class;
    }
}
