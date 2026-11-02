<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use Dompdf\Options;

/**
 * Dispatched before a PDF is rendered. Listeners may change the Dompdf `$options`.
 */
class PdfRenderOptionsResolving
{
    public function __construct(
        public Options $options,
    ) {
    }
}
