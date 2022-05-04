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
    public mixed $startDate = null;
    public mixed $endDate = null;
    public mixed $status = null;
    public mixed $orderQuery = null;
    public mixed $columns = null;
    public mixed $orders = null;
    public mixed $format = null;
}
