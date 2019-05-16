<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Variant;
use yii\base\Event;

/**
 * Class CustomizeVariantSnapshotFieldsEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomizeVariantSnapshotFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Variant The variant
     */
    public $variant;

    /**
     * @var array|null The fields to be captured
     */
    public $fields;
}
