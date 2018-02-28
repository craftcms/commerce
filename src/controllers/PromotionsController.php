<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

/**
 * Class Promotions Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PromotionsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $this->redirect('commerce/promotions/sales');
    }
}
