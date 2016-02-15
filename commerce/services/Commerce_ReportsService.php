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
     * @return array
     */
    public function getDateRanges()
    {
        $dateRanges = [
            'd7' => ['label' => Craft::t('Last 7 days'), 'startDate' => '-7 days', 'endDate' => null],
            'd30' => ['label' => Craft::t('Last 30 days'), 'startDate' => '-30 days', 'endDate' => null],
            'lastweek' => ['label' => Craft::t('Last Week'), 'startDate' => '-2 weeks', 'endDate' => '-1 week'],
            'lastmonth' => ['label' => Craft::t('Last Month'), 'startDate' => '-2 months', 'endDate' => '-1 month'],
        ];

        return $dateRanges;
    }

    /**
     * @param ElementCriteriaModel $criteria
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     */
    public function getRevenueReport($criteria, $startDate, $endDate)
    {
        $criteria->limit = null;

        $query = craft()->elements->buildElementsQuery($criteria);
        $query->select('DATE_FORMAT(orders.dateOrdered, "%Y-%m-%d") as date, sum(orders.totalPrice) as revenue');
        $query->group('YEAR(orders.dateOrdered), MONTH(orders.dateOrdered), DAY(orders.dateOrdered)');
        // $query->join('select date, revenue from DATE_ADD(date, INTERVAL expr type)');

        $results = $query->queryAll();

        $report = $this->getReportDataTable($startDate, $endDate, $results);
        $scale = $this->getScale($startDate, $endDate);


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

        $currencyFormat = $this->getCurrencyFormat();

        $response = array(
            'report' => $report,
            'scale' => $scale,
            'localeDefinition' => [
                'currencyFormat' => $currencyFormat,
            ],
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
                if($result['date'] == strftime("%Y-%m-%d", $cursorStart->getTimestamp()))
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
        $chartRows = array_merge($chartRows, array_reverse($rows));

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

        if ($numberOfDays > 360)
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
}
