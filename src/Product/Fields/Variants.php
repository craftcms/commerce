<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Fields;

use craft\commerce\gql\arguments\elements\Variant as VariantArguments;
use craft\commerce\gql\interfaces\elements\Variant as VariantInterface;
use craft\commerce\gql\resolvers\elements\Variant as VariantResolver;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Gql\Gql as GqlService;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use GraphQL\Type\Definition\Type;
use Override;

use function CraftCms\Cms\t;

class Variants extends BaseRelationField
{
    #[Override]
    public static function displayName(): string
    {
        return t('Commerce Variants', category: 'commerce');
    }

    #[Override]
    public static function icon(): string
    {
        return 'tags';
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a variant', category: 'commerce');
    }

    #[Override]
    public function getContentGqlType(): array|Type
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(VariantInterface::getType()),
            'args' => VariantArguments::getArguments(),
            'resolve' => VariantResolver::class . '::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }

    public static function elementType(): string
    {
        return Variant::class;
    }
}
