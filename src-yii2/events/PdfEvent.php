<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Pdf\Data\Pdf;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class PdfEvent extends Event
{
    /**
     * @var Pdf The PDF
     */
    public Pdf $pdf;

    /**
     * @var bool Whether the PDF is new
     */
    public bool $isNew = false;
}
