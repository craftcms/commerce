<?php

namespace craft\commerce\controllers;

/**
 * Class Promotions Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class PromotionsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    /**
     *
     */
    public function actionIndex(): Response
    {
        $this->redirect('commerce/promotions/sales');
    }
}
