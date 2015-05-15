<?php
namespace Craft;

/**
 * Class Market_PromotionsController
 *
 * @package Craft
 */
class Market_PromotionsController extends Market_BaseController
{
    public function actionIndex()
    {
        $this->redirect('market/promotions/sales');
    }
}