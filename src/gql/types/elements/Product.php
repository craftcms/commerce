<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\elements;

use craft\behaviors\DraftBehavior;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\helpers\Gql;
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
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var ProductElement|DraftBehavior $source */
        $fieldName = $resolveInfo->fieldName;
        $value = match ($fieldName) {
            'draftName', 'draftNotes' => $source->getIsDraft() ? $source->{$fieldName} : null,
            'draftCreator' => $source->getIsDraft() ? $source->getCreator() : null,
            'revisionCreator' => $source->getIsRevision() ? $source->getCreator() : null,
            'productType' => $source->getType(),
            'productTypeHandle' => $source->getType()->handle,
            'productTypeId' => $source->getType()->id,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };

        // Eager-loaded relationships bypass the root product query's provisional draft filter.
        if ($value instanceof ProductElement) {
            return Gql::canAccessProductDraft($value) ? $value : null;
        }
        if (in_array($fieldName, ['drafts', 'revisions', 'localized', 'children', 'ancestors', 'descendants'], true) && is_iterable($value)) {
            $products = [];
            foreach ($value as $product) {
                if (Gql::canAccessProductDraft($product)) {
                    $products[] = $product;
                }
            }
            return $products;
        }

        return $value;
    }
}
