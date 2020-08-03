<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;

/**
 * Class Update Order Status
 *
 * @property null|string $triggerHtml the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class DownloadOrderPdf extends ElementAction
{
    /**
     * @var int
     */
    public $pdfHandle;

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Plugin::t('Download “' . $this->_getPdf()->name . '” PDF');
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {

        return true;
    }

    /**
     *
     */
    private function _getPdf()
    {
        return Plugin::getInstance()->getPdfs()->getPdfByHandle($this->pdfHandle);
    }
}
