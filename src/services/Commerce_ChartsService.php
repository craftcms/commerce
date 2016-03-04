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
        $dataTable = $this->getRevenueDataTable($startDate, $endDate, $criteria);

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

	        'formats' => craft()->charts->getFormats(),
            'orientation' => craft()->locale->getOrientation(),
            'scale' => craft()->charts->getScale($startDate, $endDate),
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
     * @param int|null $userGroupId
     *
     * @return array Returns a data table (array of columns and rows)
     */
    private function getRevenueDataTable($startDate, $endDate, $criteria)
    {

        $scale = craft()->charts->getScale($startDate, $endDate);
        $scaleFormat = craft()->charts->getScaleDateFormat($scale);

        $criteria->limit = null;

        $query = craft()->elements->buildElementsQuery($criteria);

        $query->select('DATE_FORMAT(orders.dateOrdered, "'.$scaleFormat.'") as date, sum(orders.totalPrice) as revenue');
        $query->andWhere(array('and', 'orders.dateOrdered > :startDate', 'orders.dateOrdered < :endDate'), array(':startDate' => $startDate->mySqlDateTime(), ':endDate' => $endDate->mySqlDateTime()));

        switch ($scale)
        {
            case 'year':
                $query->group('YEAR(orders.dateOrdered)');
                break;

            case 'month':
                $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered)');
                break;

            case 'hour':
                $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered), DAY(orders.dateOrdered), HOUR(orders.dateOrdered)');
                break;

            case 'day':
                $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered), DAY(orders.dateOrdered)');
                break;
        }

        // $query->join('select date, revenue from DATE_ADD(date, INTERVAL expr type)');

        $results = $query->queryAll();

        return $this->parseResultsToDataTable($startDate, $endDate, $results);
    }

    /**
     * Returns report as a data table
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $results
     *
     * @return array
     */
    private function parseResultsToDataTable($startDate, $endDate, $results)
    {
        $scale = craft()->charts->getScale($startDate, $endDate);
	    $scaleFormat = craft()->charts->getScaleDateFormat($scale);


        // columns

        $columns = [];

        switch ($scale)
        {
            case 'hour':
                $xType = 'datetime';
                break;

            default:
                $xType = 'date';
                break;
        }

        $columns[] = [
            'type' => $xType,
            'label' => Craft::t('Date'),
        ];

        $columns[] = [
            'type' => 'currency',
            'label' => Craft::t('Revenue'),
        ];


        // rows

        $rows = [];

        $timezone = new \DateTimeZone(craft()->timezone);


        switch($scale)
        {
            case 'year':
            $cursorCurrent = new DateTime($startDate, $timezone);
            $cursorCurrent = new DateTime($cursorCurrent->format('Y-01-01'), $timezone);
            break;

            case 'month':
            $cursorCurrent = new DateTime($startDate, $timezone);
            $cursorCurrent = new DateTime($cursorCurrent->format('Y-m-01'), $timezone);
            break;

            default:
            $cursorCurrent = new DateTime($startDate, $timezone);
        }

        while($cursorCurrent->getTimestamp() < $endDate->getTimestamp())
        {
            switch($scale)
            {
                case 'hour':
                $cursorFormat = 'Y-m-d H:i';
                break;

                default:
                $cursorFormat = 'Y-m-d';
            }

            $cursorStart = new DateTime($cursorCurrent->format($cursorFormat), $timezone);
            $cursorCurrent->modify('+1 '.$scale);

            $cursorEnd = $cursorCurrent;

            $row = [
                strftime($scaleFormat, $cursorStart->getTimestamp()), // date
                0 // revenue
            ];

            foreach($results as $result)
            {
                if($result['date'] == strftime($scaleFormat, $cursorStart->getTimestamp()))
                {
                    $row = [
                        $result['date'], // date
                        (float) $result['revenue'] // revenue
                    ];
                }
            }

            $rows[] = $row;
        }

        return array(
            'columns' => $columns,
            'rows' => $rows,
        );
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
