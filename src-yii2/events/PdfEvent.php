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
 * Class PdfEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PdfEvent extends Event
{
    /**
     * @var Pdf The PDF model associated with the event.
     */
    public Pdf $pdf;

    /**
     * @var bool Whether the PDF is brand new
     */
    public bool $isNew = false;
}
