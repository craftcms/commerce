<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\records\Site;
use craft\services\Sites;
use craft\test\ActiveFixture;
use craft\test\DbFixtureTrait;

/**
 * Class SitesFixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class SitesFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $modelClass = Site::class;

    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/sites.php';

    /**
     * @inheritdoc
     */
    public function load(): void
    {
        parent::load();

        // Because the Sites() class memoizes on initialization we need to set() a new sites class
        // with the updated fixture data
        Craft::$app->set('sites', new Sites());
        Craft::$app->getIsMultiSite(true);
        Craft::$app->getIsMultiSite(true, true);
    }

    use DbFixtureTrait;
    public function unload(): void
    {
        $this->checkIntegrity(true);
        parent::unload();
        $this->checkIntegrity(false);
    }
}
