<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Fields;

use craft\commerce\gql\arguments\elements\Product as ProductArguments;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\gql\resolvers\elements\Product as ProductResolver;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Gql\Gql as GqlService;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Commerce\Product\Elements\Product;
use GraphQL\Type\Definition\Type;
use Override;

use function CraftCms\Cms\t;

/**
 * @todo the legacy `inputTemplateVariables()` hook this field used to register `CommerceCpAsset`/
 * `ProductIndexAsset` and pass a `jsSettings.productTypeId` setting to `Craft.Commerce.ProductSelectInput`
 * (auto-scoping the element-select modal to the field's configured product type source) doesn't exist
 * on the new `formControl()`-based rendering pipeline. Revisit once that pipeline exposes an equivalent
 * extension point.
 */
class Products extends BaseRelationField
{
    #[Override]
    protected ?string $inputJsClass = 'Craft.Commerce.ProductSelectInput';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Never needed and allows us to instantiate the field while ignoring old setting until the Product field migration has run.
        unset($config['targetLocale']);
        parent::__construct($config);
    }

    #[Override]
    public static function icon(): string
    {
        return 'tag';
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Commerce Products', category: 'commerce');
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a product', category: 'commerce');
    }

    #[Override]
    public function getContentGqlType(): array|Type
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(ProductInterface::getType()),
            'args' => ProductArguments::getArguments(),
            'resolve' => ProductResolver::class . '::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }

    public static function elementType(): string
    {
        return Product::class;
    }
}
