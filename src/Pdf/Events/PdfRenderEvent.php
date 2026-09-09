<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use yii\base\Event;

class PdfRenderEvent extends Event
{
    public function __construct(
        public Order $order,
        public string $option,
        public string $template,
        public array $variables,
        public ?string $pdf = null,
        public ?Pdf $sourcePdf = null,
    ) {
    }
}
