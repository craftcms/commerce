<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fields;

use Craft;
use craft\commerce\elements\Product;
use craft\fields\BaseRelationField;

/**
 * Class Product Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Products extends BaseRelationField
{
    // Public Methods
    // =========================================================================

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
        return Craft::t('commerce', 'Commerce Products');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('commerce', 'Add a product');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Product::class;
    }
}
