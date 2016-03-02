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

        $report = $this->getReportDataTable($startDate, $endDate, $results);


        // totals

        $total = 0;

        foreach($report['rows'] as $row)
        {
            $total = $total + $row[1];
        }

        $locale = craft()->i18n->getLocaleData(craft()->language);
        $orientation = $locale->getOrientation();

        $currency = craft()->commerce_settings->getOption('defaultCurrency');
        $totalHtml = craft()->numberFormatter->formatCurrency($total, strtoupper($currency));

        $response = array(
            'report' => $report,
            'scale' => $scale,
            'localeDefinition' => [
                'currency' => $this->getLocaleDefinitionCurrency(),
            ],
	        'formats' => craft()->charts->getFormats(),
            'craftCurrencyFormat' => craft()->locale->getCurrencyFormat(),
            'orientation' => $orientation,
            'total' => $total,
            'totalHtml' => $totalHtml,
        );

        return $response;
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
    private function getReportDataTable($startDate, $endDate, $results)
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

        $cursorCurrent = new DateTime($startDate);

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

            $cursorStart = new DateTime($cursorCurrent->format($cursorFormat));
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
