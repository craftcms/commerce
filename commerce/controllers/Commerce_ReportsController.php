<?php
namespace Craft;

/**
 * Class Commerce_ReportsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ReportsController extends Commerce_BaseCpController
{
    public function actionGetOrders()
    {
        $startDate = strtotime("-7 days");
        $endDate = strtotime("now");

        $data = [];
        $position = $startDate;

        while($position < $endDate)
        {
            $position += 60 * 60 * 24;

            $data[] = ['date' => strftime("%e-%b-%y", $position), 'close' => rand(0, 2000)];
        }

        $this->returnJson($data);
    }
}
