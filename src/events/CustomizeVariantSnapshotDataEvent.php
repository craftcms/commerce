<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class CustomizeVariantSnapshotDataEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomizeVariantSnapshotDataEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Variant The variant
     */
    public $variant;

    /**
     * @var array The captured data
     */
    public $fieldData;
}
