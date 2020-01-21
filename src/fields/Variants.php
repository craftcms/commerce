<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fields;

use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\fields\BaseRelationField;

/**
 * Class Variant Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variants extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t('Commerce Variants');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Plugin::t('Add a variant');
    }


    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Variant::class;
    }
}
