<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * Class PdfRenderEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class PdfRenderEvent extends Event
{
    /**
     * @var Order
     */
    public Order $order;

    /**
     * @var string
     */
    public string $option;

    /**
     * @var string
     */
    public string $template;

    /**
     * @var array
     */
    public array $variables;

    /**
     * @var string|null The rendered PDF
     */
    public ?string $pdf = null;
}
