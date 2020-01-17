<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\Plugin;
use craft\commerce\stats\TotalOrders as TotalOrdersStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;

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
    /**
     * @var int|bool
     */
    public $showChart;

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        // This widget is only available to users that can manage customers
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t( 'Total Orders');
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {
        $showChart = $this->showChart;
        $stat = new TotalOrdersStat(TotalOrdersStat::DATE_RANGE_PASTYEAR);
        $stats = $stat->get();
        $number = $stats['total'] ?? 0;
        $chart = $stats['chart'] ?? [];

        $labels = array_values(ArrayHelper::getColumn($chart, 'date'));
        $data = array_values(ArrayHelper::getColumn($chart, 'totalOrders'));

        $timeFrame = $stat->getDateRangeWording();
        $number = Craft::$app->getFormatter()->asInteger($number);

        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/Orders/total/body', compact(
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
    public static function maxColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/Orders/total/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
