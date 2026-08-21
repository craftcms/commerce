<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Elements;

use CraftCms\Cms\Gql\Types\Elements\Element;
use CraftCms\Commerce\Catalog\Elements\Variant as VariantElement;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Override;

class Variant extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            VariantInterface::getType(),
        ];

        parent::__construct($config);
    }

    #[Override]
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var VariantElement $source */
        $fieldName = $resolveInfo->fieldName;
        $product = $source->getOwner();
        return match ($fieldName) {
            'productTitle' => $product->title ?? '',
            'productTypeId' => $product->typeId ?? null,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
