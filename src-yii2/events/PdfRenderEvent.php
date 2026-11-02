<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class PdfRenderEvent extends Event
{
    /**
     * @var Order The order
     */
    public Order $order;

    /**
     * @var string The PDF option
     */
    public string $option;

    /**
     * @var string The template path
     */
    public string $template;

    /**
     * @var array The template variables
     */
    public array $variables;

    /**
     * @var string|null The rendered PDF
     */
    public ?string $pdf = null;

    /**
     * @var Pdf|null The PDF model being rendered
     */
    public ?Pdf $sourcePdf = null;
}
