<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\handlers;

use CraftCms\Cms\Gql\Handlers\ArgumentHandler;

/**
 * Class HasProduct
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.5
 */
class HasProduct extends ArgumentHandler
{
    #[\Override]
    protected string $argumentName = 'hasProduct';

    #[\Override]
    protected function handleArgument(mixed $argumentValue): mixed
    {
        if (is_array($argumentValue)) {
            return $this->argumentManager->prepareArguments($argumentValue);
        }

        return $argumentValue;
    }
}
