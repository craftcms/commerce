<?php

namespace craft\commerce\events;

use Dompdf\Options;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Pdf\Events\PdfRenderOptionsResolving} instead.
 */
class PdfRenderOptionsEvent extends Event
{
    /**
     * @var Options The Dompdf options
     */
    public Options $options;
}
