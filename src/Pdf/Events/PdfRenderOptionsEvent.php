<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use Dompdf\Options;
use yii\base\Event;

class PdfRenderOptionsEvent extends Event
{
    public function __construct(
        public Options $options,
    ) {
    }
}
