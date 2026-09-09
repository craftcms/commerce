<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Elements;

use CraftCms\Cms\Gql\Types\Elements\Element;
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Product\Elements\Product as ProductElement;
use GraphQL\Type\Definition\ResolveInfo;
use Override;

class Product extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            ProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    #[Override]
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var ProductElement $source */
        $fieldName = $resolveInfo->fieldName;
        return match ($fieldName) {
            'productTypeHandle' => $source->getType()->handle,
            'productTypeId' => $source->getType()->id,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
