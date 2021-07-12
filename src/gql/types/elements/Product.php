<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\elements;

use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\gql\types\elements\Element as ElementType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            ProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        /** @var ProductElement $source */
        $fieldName = $resolveInfo->fieldName;

        switch ($fieldName) {
            case 'productTypeHandle':
                return $source->getType()->handle;
            case 'productTypeId':
                return $source->getType()->id;
        }

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}
