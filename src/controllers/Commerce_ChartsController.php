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
     * Returns the data needed to display a Revenue chart.
     *
     * @return void
     */
    public function actionGetRevenueData()
    {
        $startDateParam = craft()->request->getRequiredPost('startDate');
        $endDateParam = craft()->request->getRequiredPost('endDate');

        $startDate = DateTime::createFromString($startDateParam, craft()->timezone);
        $endDate = DateTime::createFromString($endDateParam, craft()->timezone);
        $endDate->modify('+1 day');

        $intervalUnit = ChartHelper::getRunChartIntervalUnit($startDate, $endDate);

        // Prep the query
        $criteria = $this->getElementCriteria();
        $criteria->limit = null;

        // Don't use the search
        $criteria->search = null;

        $query = craft()->elements->buildElementsQuery($criteria)
            ->select('sum(orders.totalPrice) as value');

        // Get the chart data table
        $dataTable = ChartHelper::getRunChartDataFromQuery($query, $startDate, $endDate, 'orders.dateOrdered', [
            'intervalUnit' => $intervalUnit,
            'valueLabel' => Craft::t('Revenue'),
            'valueType' => 'currency',
        ]);

        // Get the total revenue
        $total = 0;

        foreach($dataTable['rows'] as $row)
        {
            $total = $total + $row[1];
        }

        // Return everything
        $currency = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();
        $totalHtml = craft()->numberFormatter->formatCurrency($total, strtoupper($currency));

        $this->returnJson(array(
            'dataTable' => $dataTable,
            'total' => $total,
            'totalHtml' => $totalHtml,

            'formats' => ChartHelper::getFormats(),
            'orientation' => craft()->locale->getOrientation(),
            'scale' => $intervalUnit,
            'localeDefinition' => [
                'currency' => $this->_getLocaleDefinitionCurrency(),
            ],
        ));
    }

    /**
     * Returns D3 currency format locale definition.
     *
     * @return string
     */
    private function _getLocaleDefinitionCurrency()
    {
        $currency = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();

        $currencySymbol = craft()->locale->getCurrencySymbol($currency);
        $currencyFormat = craft()->locale->getCurrencyFormat();

        if(strpos($currencyFormat, ";") > 0)
        {
            $currencyFormatArray = explode(";", $currencyFormat);
            $currencyFormat = $currencyFormatArray[0];
        }

        $pattern = '/[#0,.]/';
        $replacement = '';
        $currencyFormat = preg_replace($pattern, $replacement, $currencyFormat);

        if(strpos($currencyFormat, "¤") === 0)
        {
            // symbol at beginning
            $currencyD3Format = [str_replace('¤', $currencySymbol, $currencyFormat), ''];
        }
        else
        {
            // symbol at the end
            $currencyD3Format = ['', str_replace('¤', $currencySymbol, $currencyFormat)];
        }

        return $currencyD3Format;
    }
}
