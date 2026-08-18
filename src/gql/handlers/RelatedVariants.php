<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Handlers;

use CraftCms\Cms\Gql\Handlers\RelationArgumentHandler;
use CraftCms\Commerce\Catalog\Elements\Variant;

class RelatedVariants extends RelationArgumentHandler
{
    #[\Override]
    protected string $argumentName = 'relatedToVariants';

    #[\Override]
    protected function handleArgument($argumentValue): mixed
    {
        $argumentValue = parent::handleArgument($argumentValue);
        return $this->getIds(Variant::class, $argumentValue);
    }
}
