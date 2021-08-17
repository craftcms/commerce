<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Pdf;
use yii\base\Event;

/**
 * Class PdfSaveEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PdfSaveEvent extends Event
{
    /**
     * @var Pdf
     */
    public Pdf $pdf;

    /**
     * @var bool Is the PDF new
     */
    public bool $isNew;
}
