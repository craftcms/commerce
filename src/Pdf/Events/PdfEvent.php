<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use CraftCms\Commerce\Pdf\Data\Pdf;
use yii\base\Event;

class PdfEvent extends Event
{
    public function __construct(
        public Pdf $pdf,
        public bool $isNew = false,
    ) {
    }
}
