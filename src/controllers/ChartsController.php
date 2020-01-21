<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\controllers\ElementIndexesController;
use craft\elements\db\ElementQuery;
use craft\helpers\ChartHelper;
use craft\helpers\DateTimeHelper;
use craft\i18n\Locale;

/**
 * Class Charts Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ChartsController extends ElementIndexesController
{
    /**
     * Returns the data needed to display a Revenue chart.
     */
    public function actionGetRevenueData()
    {
        $source = $this->source;
        $data = $source['data'];
        $dateAttr = $data['date-attr'] ?? null;

        // set default date columm
        if (!$dateAttr) {
            $dateAttr = 'dateUpdated';
        }

        // set default time period of 30 days for the chart
        $startDate = new \DateTime();
        $startDate->modify('-30 day');
        $endDate = new \DateTime();

        $dateParam = Craft::$app->getRequest()->getParam('criteria.' . $dateAttr);

        if ($dateParam) {
            // $dateParam[0] is the AND condition
            $startDate = substr($dateParam[1], 2);
            $endDate = substr($dateParam[2], 1);
            $startDate = DateTimeHelper::toDateTime($startDate, true);
            $endDate = DateTimeHelper::toDateTime($endDate, true);
        }

        $intervalUnit = ChartHelper::getRunChartIntervalUnit($startDate, $endDate);

        // always add 24 hours to the end date
        $endDate->modify('+1 day');

        /** @var ElementQuery $query */
        $query = clone $this->getElementQuery()->search(null);

        // Remove the date range in the element query, we have already extracted it.
         $query->$dateAttr = null;

        // Get the chart data table
        $dataTable = ChartHelper::getRunChartDataFromQuery($query, $startDate, $endDate, 'commerce_orders.' . $dateAttr, 'sum', '[[commerce_orders.totalPrice]]', [
            'intervalUnit' => $intervalUnit,
            'valueLabel' => Plugin::t('Revenue'),
            'valueType' => 'currency',
        ]);

        // Get the total revenue
        $total = 0;

        foreach ($dataTable['rows'] as $row) {
            $total += $row[1];
        }

        // Return everything
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $totalHtml = Craft::$app->getFormatter()->asCurrency($total, strtoupper($currency));

        $data = $this->asJson([
            'dataTable' => $dataTable,
            'total' => $total,
            'totalHtml' => $totalHtml,

            'formats' => ChartHelper::formats(),
            'orientation' => Craft::$app->getLocale()->getOrientation(),
            'scale' => $intervalUnit,
            'formatLocaleDefinition' => [
                'currency' => $this->_getLocaleDefinitionCurrency(),
            ],
        ]);

        return $data;
    }


    /**
     * Returns D3 currency format locale definition.
     *
     * @return array
     */
    private function _getLocaleDefinitionCurrency(): array
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        $currencySymbol = Craft::$app->getLocale()->getCurrencySymbol($currency);
        $currencyFormat = Craft::$app->getLocale()->getNumberPattern(Locale::STYLE_CURRENCY);

        if (strpos($currencyFormat, ';') > 0) {
            $currencyFormatArray = explode(';', $currencyFormat);
            $currencyFormat = $currencyFormatArray[0];
        }

        $pattern = '/[#0,.]/';
        $replacement = '';
        $currencyFormat = preg_replace($pattern, $replacement, $currencyFormat);

        if (strpos($currencyFormat, '¤') === 0) {
            // symbol at beginning
            $currencyD3Format = [str_replace('¤', $currencySymbol, $currencyFormat), ''];
        } else {
            // symbol at the end
            $currencyD3Format = ['', str_replace('¤', $currencySymbol, $currencyFormat)];
        }

        return $currencyD3Format;
    }
}
