<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fields;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\gql\arguments\elements\Product as ProductArguments;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\gql\resolvers\elements\Product as ProductResolver;
use craft\commerce\Plugin;
use craft\commerce\web\assets\editproduct\EditProductAsset;
use craft\fields\BaseRelationField;
use GraphQL\Type\Definition\Type;

/**
 * Class Product Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Products extends BaseRelationField
{
    public function __construct(array $config = [])
    {
        // Never needed and allows us to instantiate the field while ignoring old setting until the Product field migration has run.
        unset($config['targetLocale']);
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t('Commerce Products');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Plugin::t('Add a product');
    }

    public function getInputHtml($value, ElementInterface $element = null): string
    {
        Craft::$app->getView()->registerAssetBundle(EditProductAsset::class);
        return parent::getInputHtml($value, $element);
    }

    /**
     * @inheritdoc
     * @since 3.1.4
     */
    public function getContentGqlType()
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(ProductInterface::getType()),
            'args' => ProductArguments::getArguments(),
            'resolve' => ProductResolver::class . '::resolve',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Product::class;
    }
}
