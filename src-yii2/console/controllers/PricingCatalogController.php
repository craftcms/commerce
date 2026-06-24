<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use craft\commerce\console\Controller;
use craft\commerce\Plugin;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Manage the pricing catalog.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PricingCatalogController extends Controller
{
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
