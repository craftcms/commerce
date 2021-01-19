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
use craft\services\ProjectConfig;

/**
 * Class EmailsFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.x
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
    public $saveMethod = 'saveEmail';

    /**
     * @inheritDoc
     */
    public $deleteMethod = 'deleteEmailById';

    /**
     * @inheritDoc
     */
    public $service = 'emails';

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }
}
