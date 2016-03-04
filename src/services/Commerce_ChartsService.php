<?php
namespace Craft;

/**
 * Reports service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ChartsService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    /**
     * Returns revenue report based on a criteria, start date and end date
     *
     * @param ElementCriteriaModel $criteria
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     */
    public function getRevenueReport($criteria, $startDate, $endDate)
    {
	    $intervalUnit = ChartHelper::getRunChartIntervalUnit($startDate, $endDate);
        $dataTable = $this->getRevenueDataTable($startDate, $endDate, $intervalUnit, $criteria);

        $total = 0;

        foreach($dataTable['rows'] as $row)
        {
            $total = $total + $row[1];
        }

        $currency = craft()->commerce_settings->getOption('defaultCurrency');
        $totalHtml = craft()->numberFormatter->formatCurrency($total, strtoupper($currency));

        return array(
            'dataTable' => $dataTable,
            'total' => $total,
            'totalHtml' => $totalHtml,

	        'formats' => ChartHelper::getFormats(),
            'orientation' => craft()->locale->getOrientation(),
            'scale' => $intervalUnit,
            'localeDefinition' => [
                'currency' => $this->getLocaleDefinitionCurrency(),
            ],
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the revenue as a data table
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $intervalUnit
     * @param int|null $userGroupId
     *
     * @return array Returns a data table (array of columns and rows)
     */
    private function getRevenueDataTable($startDate, $endDate, $intervalUnit, $criteria)
    {
        // Convert the criteria into an element query
        $criteria->limit = null;
        $query = craft()->elements->buildElementsQuery($criteria)
            ->select('sum(orders.totalPrice) as value');

        return ChartHelper::getRunChartDataFromQuery($query, $startDate, $endDate, 'orders.dateOrdered', [
	        'intervalUnit' => $intervalUnit,
	        'valueLabel' => Craft::t('Revenue'),
	        'valueType' => 'currency',
        ]);
    }

	/**
     * Returns D3 currency format locale definition
     *
	 * @param string $currency
	 *
	 * @return string
	 */
	private function getLocaleDefinitionCurrency()
	{
		$currency = craft()->commerce_settings->getOption('defaultCurrency');

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
