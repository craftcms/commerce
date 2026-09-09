<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Handlers;

use CraftCms\Cms\Gql\Handlers\RelationArgumentHandler;
use CraftCms\Commerce\Product\Elements\Product;

class RelatedProducts extends RelationArgumentHandler
{
    #[\Override]
    protected string $argumentName = 'relatedToProducts';

    #[\Override]
    protected function handleArgument($argumentValue): mixed
    {
        $argumentValue = parent::handleArgument($argumentValue);
        return $this->getIds(Product::class, $argumentValue);
    }
}
