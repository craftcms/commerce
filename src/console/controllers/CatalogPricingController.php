<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\elements\Category;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\records\UserGroup_User;
use DateTime;
use yii\console\ExitCode;

/**
 * Console command to generate catalog pricing.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class CatalogPricingController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'generate';

    private ?array $_allSales = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return $options;
    }

    /**
     * Generates catalog pricing.
     */
    public function actionGenerate(): int
    {
        $this->stdout('Generating catalog pricing... ');

        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices(showConsoleOutput: true);

        $this->_done();
        return ExitCode::OK;
    }

    private function _done(): void
    {
        $this->stdout('Done!' . PHP_EOL, Console::FG_GREEN);
    }
}
