<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class ReportEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ReportEvent extends Event
{
    // Properties
    // =========================================================================

    public $startDate;
    public $endDate;
    public $status;
    public $orderQuery;
    public $columns;
    public $orders;
    public $format;
}
