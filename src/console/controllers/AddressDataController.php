<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use craft\commerce\console\Controller;
use yii\console\ExitCode;

/**
 * Allows you to populate Commerce Address data.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressDataController extends Controller
{
    /**
     * 
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout('da');
        
        return ExitCode::OK;
    }
}
