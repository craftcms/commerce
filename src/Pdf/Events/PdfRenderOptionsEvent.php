<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use Dompdf\Options;

class PdfRenderOptionsEvent
{
    public Options $options;
}
