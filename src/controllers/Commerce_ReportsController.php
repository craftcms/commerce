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
        $data = [];

        $startDate = craft()->request->getParam('startDate');
        $endDate = craft()->request->getParam('endDate');

        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        $endDate->modify('+1 day');
        $scale = 'day';


        $cursorTimestamp = $startDate->getTimestamp();

        while($cursorTimestamp < $endDate->getTimestamp())
        {
            $cursorStart = new DateTime();
            $cursorStart->setTimestamp($cursorTimestamp);

            $cursorTimestamp += (60 * 60 * 24);

            $cursorEnd = new DateTime();
            $cursorEnd->setTimestamp($cursorTimestamp);

            $orders = $this->_getOrders($cursorStart, $cursorEnd);

            $totalPaid = 0;

            foreach($orders as $order)
            {
                $totalPaid += $order->totalPaid;
            }

            $data[] = ['date' => strftime("%e-%b-%y", $cursorStart->getTimestamp()), 'close' => $totalPaid];
        }

        $this->returnJson($data);
    }

    private function _getOrders($start, $end)
    {
        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->completed = true;
        $criteria->dateOrdered = ['and', '>= '.$start, '< '.$end];
        $criteria->order = 'dateOrdered desc';

        return $criteria->find();
    }
}
