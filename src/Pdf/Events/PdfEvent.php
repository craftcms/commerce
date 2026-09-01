<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use CraftCms\Commerce\Pdf\Data\Pdf;

class PdfEvent
{
    public function __construct(
        public Pdf $pdf,
        public bool $isNew = false,
    ) {
    }
}
