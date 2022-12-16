<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\web\Response;

/**
 * Class Variants Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class VariantsController extends BaseController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/variants/_index');
    }
}