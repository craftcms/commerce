<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\Pdf;
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

    /**
     * @var Pdf|null The configured PDF model used to render the PDF
     */
    public ?Pdf $sourcePdf = null;
}
