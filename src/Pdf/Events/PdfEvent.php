<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Events;

use craft\commerce\models\Pdf;

class PdfEvent
{
    public Pdf $pdf;
    public bool $isNew = false;
}
