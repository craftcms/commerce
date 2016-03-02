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
        $startDate = craft()->request->getParam('startDate');
        $endDate = (craft()->request->getParam('endDate') ? craft()->request->getParam('endDate') : 'now');

	    $timezone = new \DateTimeZone(craft()->timezone);

        $startDate = new DateTime($startDate, $timezone);

        $endDate = new Datetime($endDate, $timezone);
        $endDate->modify('+1 day');

        $criteria = $this->getElementCriteria();

        $revenueReport = craft()->commerce_charts->getRevenueReport($criteria, $startDate, $endDate);

        $this->returnJson($revenueReport);
    }
}
