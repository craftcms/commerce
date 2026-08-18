<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Models\Pdf;

class PdfRenderEvent
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
