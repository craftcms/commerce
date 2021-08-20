<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * ModifyFormAttributesEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class ModifyFormAttributesEvent extends Event
{
    /**
     * @var array The form attributes is allowed to be modified
     */
    public array $attributes = [];
}
