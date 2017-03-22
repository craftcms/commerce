<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\controllers\ElementIndexesController;
use craft\helpers\ChartHelper;
use craft\helpers\DateTimeHelper;

/**
 * Class Charts Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Charts extends ElementIndexesController
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the data needed to display a Revenue chart.
     *
     * @return void
     */
    public function actionGetRevenueData()
    {
        $startDateParam = Craft::$app->getRequest()->getRequiredParam('startDate');
        $endDateParam = Craft::$app->getRequest()->getRequiredParam('endDate');

        $startDate = DateTimeHelper::toDateTime($startDateParam);
        $endDate = DateTimeHelper::toDateTime($endDateParam);
        $endDate->modify('+1 day');

        $intervalUnit = ChartHelper::getRunChartIntervalUnit($startDate, $endDate);

        // Prep the query
        $criteria = $this->getElementCriteria();
        $criteria->limit = null;

        // Don't use the search
        $criteria->search = null;

        $query = Craft::$app->getElements()->buildElementsQuery($criteria)
            ->select('sum(orders.totalPrice) as value');

        // Get the chart data table
        $dataTable = ChartHelper::getRunChartDataFromQuery($query, $startDate, $endDate, 'orders.dateOrdered', [
            'intervalUnit' => $intervalUnit,
            'valueLabel' => Craft::t('commerce', 'Revenue'),
            'valueType' => 'currency',
        ]);

        // Get the total revenue
        $total = 0;

        foreach ($dataTable['rows'] as $row) {
            $total = $total + $row[1];
        }

        // Return everything
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $totalHtml = Craft::$app->getFormatter()->asCurrency($total, strtoupper($currency));

        $this->asJson([
            'dataTable' => $dataTable,
            'total' => $total,
            'totalHtml' => $totalHtml,

            'formats' => ChartHelper::formats(),
            'orientation' => Craft::$app->getLocale()->getOrientation(),
            'scale' => $intervalUnit,
            'localeDefinition' => [
                'currency' => $this->_getLocaleDefinitionCurrency(),
            ],
        ]);
    }

    /**
     * Returns D3 currency format locale definition.
     *
     * @return string
     */
    private function _getLocaleDefinitionCurrency()
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        $currencySymbol = Craft::$app->getLocale()->getCurrencySymbol($currency);
        $currencyFormat = Craft::$app->getLocale()->getCurrencyFormat();

        if (strpos($currencyFormat, ";") > 0) {
            $currencyFormatArray = explode(";", $currencyFormat);
            $currencyFormat = $currencyFormatArray[0];
        }

        $pattern = '/[#0,.]/';
        $replacement = '';
        $currencyFormat = preg_replace($pattern, $replacement, $currencyFormat);

        if (strpos($currencyFormat, "¤") === 0) {
            // symbol at beginning
            $currencyD3Format = [str_replace('¤', $currencySymbol, $currencyFormat), ''];
        } else {
            // symbol at the end
            $currencyD3Format = ['', str_replace('¤', $currencySymbol, $currencyFormat)];
        }

        return $currencyD3Format;
    }
}
