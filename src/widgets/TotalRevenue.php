<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\helpers\Currency;
use craft\commerce\stats\TotalRevenue as TotalRevenueStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

/**
 * Total Revenue widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property-read string $subtitle
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalRevenue extends Widget
{

    /**
     * @var int|\DateTime|null
     */
    public $startDate;

    /**
     * @var int|\DateTime|null
     */
    public $endDate;

    /**
     * @var string|null
     */
    public ?string $dateRange = null;

    /**
     * @var bool
     */
    public bool $showOrderCount = false;

    /**
     * @var TotalRevenueStat
     */
    private TotalRevenueStat $_stat;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();
        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TotalRevenueStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TotalRevenueStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true)
        );
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Total Revenue');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        $stats = $this->_stat->get();
        $revenue = ArrayHelper::getColumn($stats, 'revenue', false);
        $total = round(array_sum($revenue), 0, PHP_ROUND_HALF_DOWN);

        $formattedTotal = Currency::formatAsCurrency($total, null, false, true, true);

        return Craft::t('commerce', '{total} in total revenue', ['total' => $formattedTotal]);
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): string
    {
        return $this->_stat->getDateRangeWording();
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $stats = $this->_stat->get();
        $timeFrame = $this->_stat->getDateRangeWording();
        $chartInterval = $this->_stat->getDateRangeInterval();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        $id = 'total-revenue' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        if (empty($stats)) {
            return Html::tag('p', Craft::t('commerce', 'No stats available.'), ['class' => 'zilch']);
        }

        $labels = ArrayHelper::getColumn($stats, 'datekey', false);
        if ($this->_stat->getDateRangeInterval() == 'month') {
            $labels = array_map(static function($label) {
                [$year, $month] = explode('-', $label);
                $month = $month < 10 ? '0' . $month : $month;
                return implode('-', [$year, $month, '01']);
            }, $labels);
        } else if ($this->_stat->getDateRangeInterval() == 'week') {
            $labels = array_map(static function($label) {
                $year = substr($label, 0, 4);
                $week = substr($label, -2);
                return $year . 'W' . $week;
            }, $labels);
        }

        $revenue = ArrayHelper::getColumn($stats, 'revenue', false);
        $orderCount = ArrayHelper::getColumn($stats, 'count', false);
        $widget = $this;

        return $view->renderTemplate('commerce/_components/widgets/orders/revenue/body',
            compact(
                'widget',
                'stats',
                'timeFrame',
                'namespaceId',
                'labels',
                'revenue',
                'orderCount',
                'chartInterval'
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $id = 'total-revenue' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/revenue/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
