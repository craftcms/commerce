<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\handlers;

use craft\gql\base\ArgumentHandler;

/**
 * Class HasVariant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.5
 */
class HasVariant extends ArgumentHandler
{
    protected string $argumentName = 'hasVariant';

    /**
     * @inheritdoc
     */
    protected function handleArgument(mixed $argumentValue): mixed
    {
        if (is_array($argumentValue)) {
            return $this->argumentManager->prepareArguments($argumentValue);
        }

        return $argumentValue;
    }
}
