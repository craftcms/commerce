<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\elements;

use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\gql\interfaces\elements\Variant as VariantInterface;
use craft\gql\types\elements\Element as ElementType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Variant extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            VariantInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var VariantElement $source */
        $fieldName = $resolveInfo->fieldName;
        $product = $source->getOwner();
        return match ($fieldName) {
            'productTitle' => $product->title ?? '',
            'productTypeId' => $product->typeId ?? null,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
