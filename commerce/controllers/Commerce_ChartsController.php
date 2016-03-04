<?php
namespace Craft;

/**
 * Class Commerce_ChartsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ChartsController extends ElementIndexController
{
    // Public Methods
    // =========================================================================

    /**
     * Get Revenue Report
     *
     * @return null
     */
    public function actionGetRevenueReport()
    {
        $startDateParam = craft()->request->getRequiredPost('startDate');
        $endDateParam = craft()->request->getRequiredPost('endDate');

        $startDate = DateTime::createFromString($startDateParam, craft()->timezone);
        $endDate = DateTime::createFromString($endDateParam, craft()->timezone);
        $endDate->modify('+1 day');

        $criteria = $this->getElementCriteria();

        $revenueReport = craft()->commerce_charts->getRevenueReport($criteria, $startDate, $endDate);

        $this->returnJson($revenueReport);
    }
}
