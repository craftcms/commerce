<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\handlers;

use craft\commerce\elements\Product;
use craft\gql\base\RelationArgumentHandler;

/**
 * Class RelatedProducts
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.0
 */
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
