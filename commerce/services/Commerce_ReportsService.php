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
class Commerce_ReportsService extends BaseApplicationComponent
{
    /**
     * @param ElementCriteriaModel $criteria
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     */
    public function getRevenueReport($criteria, $startDate, $endDate)
    {
        $scale = $this->getScale($startDate, $endDate);
	    $scaleFormat = $this->getScaleDateFormat($scale);

        $criteria->limit = null;

        $query = craft()->elements->buildElementsQuery($criteria);

	    $query->select('DATE_FORMAT(orders.dateOrdered, "'.$scaleFormat.'") as date, sum(orders.totalPrice) as revenue');

        switch ($scale)
        {
	        case 'year':
		        $query->group('YEAR(orders.dateOrdered)');
		        break;

	        case 'month':
		        $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered)');
		        break;

            default:
                $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered), DAY(orders.dateOrdered)');
                break;
        }

        // $query->join('select date, revenue from DATE_ADD(date, INTERVAL expr type)');
        $results = $query->queryAll();

        $report = $this->getReportDataTable($startDate, $endDate, $results);



        // totals

        $total = 0;

        foreach($report as $row)
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
                'currencyFormat' => $this->getCurrencyFormat(),
            ],
	        'numberFormat' => $this->getNumberFormat(),
            'orientation' => $orientation,
            'total' => $total,
            'totalHtml' => $totalHtml,
        );

        return $response;
    }

    /**
     * @param string $startDate
     * @param string $endDate
     * @param array $results
     *
     * @return array
     */
    public function getReportDataTable($startDate, $endDate, $results)
    {
        $scale = $this->getScale($startDate, $endDate);
	    $scaleFormat = $this->getScaleDateFormat($scale);

        // columns

        $columns = [];

        $columns[] = [
            'type' => 'date',
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
            $cursorStart = new DateTime($cursorCurrent);
            $cursorCurrent->modify('+1 '.$scale);
            $cursorEnd = $cursorCurrent;

            $row = [
                strftime("%Y-%m-%d", $cursorStart->getTimestamp()), // date
                0 // revenue
            ];

            foreach($results as $result)
            {
                if($result['date'] == strftime($scaleFormat, $cursorStart->getTimestamp()))
                {
                    $row = [
                        $result['date'], // date
                        $result['revenue'] // revenue
                    ];
                }
            }

            $rows[] = $row;
        }

        $chartColumns = [];
        $chartRows = [];

        foreach($columns as $column)
        {
            $chartColumns[] = $column['label'];
        }

        $chartRows = [$chartColumns];
        $chartRows = array_merge($chartRows, $rows);

        return $chartRows;
    }

    /**
     * @param string $startDate
     * @param string $endDate
     *
     * @return string
     */
    public function getScale($startDate, $endDate)
    {
        // auto scale

        $numberOfDays = floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / (60*60*24));

        if ($numberOfDays > (360 * 2))
        {
            $scale = 'year';
        }
        elseif($numberOfDays > 60)
        {
            $scale = 'month';
        }
        else
        {
            $scale = 'day';
        }

        return $scale;
    }

	/**
	 * @param string $scale
	 *
	 * @return string
	 */
	public function getScaleDateFormat($scale)
	{
		switch ($scale)
		{
			case 'year':
				return "%Y-01-01";
				break;
			case 'month':
				return "%Y-%m-01";
				break;

			default:
				return "%Y-%m-%d";
				break;
		}
	}

	/**
	 * @param string $currency
	 *
	 * @return string
	 */
	public function getCurrencyFormat()
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

	/**
	 * @param string $currency
	 *
	 * @return string
	 */
	public function getNumberFormat()
	{
		$currency = craft()->commerce_settings->getOption('defaultCurrency');

		$currencySymbol = craft()->locale->getCurrencySymbol($currency);
		$currencyFormat = craft()->locale->getCurrencyFormat();

		if(strpos($currencyFormat, ";") > 0)
		{
			$currencyFormatArray = explode(";", $currencyFormat);
			$currencyFormat = $currencyFormatArray[0];
		}

		$numberFormat = str_replace('¤', '', $currencyFormat);
		$numberFormat = trim($numberFormat);

		$yiiToD3Formats = array(
			'#,##,##0.00' => ',.2f',
			'#,##0.00' => ',.2f',
			'#0.00' => '.2f',
		);

		if(isset($yiiToD3Formats[$numberFormat]))
		{
			return $yiiToD3Formats[$numberFormat];
		}
	}
}
