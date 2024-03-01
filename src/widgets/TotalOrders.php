<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\base\StatWidgetTrait;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\stats\TotalOrders as TotalOrdersStat;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\models\Site;

/**
 * Total Orders widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrders extends Widget
{
    use StatWidgetTrait;

    /**
     * @var int|bool
     */
    public mixed $showChart = null;

    /**
     * @var null|TotalOrdersStat
     */
    private ?TotalOrdersStat $_stat = null;

    public function init(): void
    {
        parent::init();

        if (!(isset($this->storeId)) || !$this->storeId) {
            /** @var Site|StoreBehavior $site */
            $site = Cp::requestedSite();
            $this->storeId = $site->getStore()->id;
        }

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TotalOrdersStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TotalOrdersStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId
        );

        if (!empty($this->orderStatuses)) {
            $this->_stat->setOrderStatuses($this->orderStatuses);
        }
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
        return Craft::t('commerce', 'Total Orders');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        $stats = $this->_stat->get();
        $total = $stats['total'] ?? 0;
        $total = Craft::$app->getFormatter()->asInteger($total);

        return Craft::t('commerce', '{total} orders', ['total' => $total]);
    }

    public function getSubtitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        return $this->_stat->getDateRangeWording();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $showChart = $this->showChart;
        $stats = $this->_stat->get();

        if (empty($stats)) {
            return Html::tag('p', Craft::t('commerce', 'No stats available.'), ['class' => 'zilch']);
        }

        $number = $stats['total'] ?? 0;
        $chart = $stats['chart'] ?? [];

        $labels = ArrayHelper::getColumn($chart, 'datekey', false);
        $data = ArrayHelper::getColumn($chart, 'total', false);

        $timeFrame = $this->_stat->getDateRangeWording();
        $number = Craft::$app->getFormatter()->asInteger($number);

        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/orders/total/body', compact(
            'namespaceId',
            'number',
            'timeFrame',
            'labels',
            'data',
            'showChart'
        ));
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan(): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/total/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'widget' => $this,
        ]);
    }
}
