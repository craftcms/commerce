<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\Types\Elements\Element;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use GraphQL\Type\Definition\ResolveInfo;
use Override;

class DigitalProductGqlType extends Element
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            DigitalProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    #[Override]
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var DigitalProduct $source */
        return match ($resolveInfo->fieldName) {
            'productTypeHandle' => $source->getType()->handle,
            'productTypeId' => $source->typeId,
            'price' => $source->getPrice(),
            'priceAsCurrency' => $source->getPriceAsCurrency(),
            'sku' => $source->getSkuAsText(),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
