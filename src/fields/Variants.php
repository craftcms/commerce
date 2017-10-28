<?php

namespace craft\commerce\fields;

use Craft;
use craft\commerce\elements\Variant;
use craft\fields\BaseRelationField;

/**
 * Class Variant Field
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 *
 */
class Variants extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName(): string
    {
        return Craft::t('commerce', 'Commerce Variants');
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Variant::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('commerce', 'Add a variant');
    }
}
