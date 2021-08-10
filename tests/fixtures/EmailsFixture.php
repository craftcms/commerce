<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\models\Email;
use craft\commerce\Plugin;

/**
 * Class EmailsFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.2.14.1
 */
class EmailsFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/emails.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Email::class;

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveEmail';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteEmailById';

    /**
     * @inheritDoc
     */
    public $service = 'emails';

    private $_muteEvents;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function beforeUnload()
    {
        parent::beforeUnload();

        // TODO remove this when we figure out why things are being unlaoded twice #COM-54
        $this->_muteEvents = Craft::$app->getProjectConfig()->muteEvents;
        Craft::$app->getProjectConfig()->muteEvents = true;
    }

    /**
     * @inheritDoc
     */
    public function afterUnload()
    {
        parent::afterUnload();

        Craft::$app->getProjectConfig()->muteEvents = $this->_muteEvents;
    }
}
