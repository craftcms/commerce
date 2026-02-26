<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\handlers;

use craft\commerce\elements\Variant;
use craft\gql\base\RelationArgumentHandler;

/**
 * Class RelatedVariants
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.0
 */
class RelatedVariants extends RelationArgumentHandler
{
    protected string $argumentName = 'relatedToVariants';

    /**
     * @inheritdoc
     */
    protected function handleArgument($argumentValue): mixed
    {
        $argumentValue = parent::handleArgument($argumentValue);
        return $this->getIds(Variant::class, $argumentValue);
    }
}
