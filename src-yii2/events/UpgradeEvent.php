<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class UpgradeEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class UpgradeEvent extends Event
{
    /**
     * @var array $columns
     */
    public array $v3columnMap = [];

    /**
     * @var array $v3tables
     */
    public array $v3tables = [];
}
