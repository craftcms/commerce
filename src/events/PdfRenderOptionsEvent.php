<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use Dompdf\Options;
use yii\base\Event;

/**
 * Class PdfRenderOptionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class PdfRenderOptionsEvent extends Event
{

    /**
     * @var Options
     */
    public $options;
}
