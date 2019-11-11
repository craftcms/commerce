<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class SaleEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class SaleEvent extends Event
{
    // Properties
    // ==========================================================================

    /**
     * @var Sale sale
     */
    public $sale;

    /**
     * @var bool Whether the sale is brand new
     */
    public $isNew = false;
}
