<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Handlers;

use CraftCms\Cms\Gql\Handlers\ArgumentHandler;

class HasVariant extends ArgumentHandler
{
    #[\Override]
    protected string $argumentName = 'hasVariant';

    #[\Override]
    protected function handleArgument(mixed $argumentValue): mixed
    {
        if (is_array($argumentValue)) {
            return $this->argumentManager->prepareArguments($argumentValue);
        }

        return $argumentValue;
    }
}
