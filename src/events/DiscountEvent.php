<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Discount;
use yii\base\Event;

/**
 * Class DiscountEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DiscountEvent extends Event
{
    /**
     * @var Discount The discount model
     */
    public $discount;

    /**
     * @var bool If this is a new discount
     */
    public $isNew;
}
