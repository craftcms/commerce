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
use craft\commerce\stats\TotalRevenue as TotalRevenueStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;

/**
 * Total Revenue widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalRevenue extends Widget
{
    // Properties
    // =========================================================================

    // Public Methods
    // =========================================================================

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
        return Plugin::t( 'Total Revenue');
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
    public function getBodyHtml()
    {
        $stat = new TotalRevenueStat(TotalRevenueStat::DATE_RANGE_PASTYEAR);
        $stats = $stat->get();
        $timeFrame = $stat->getDateRangeWording();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);
        $view->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js');

        $id = 'total-revenue' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        if (empty($stats)) {
            // TODO no stats available message
            return '';
        }

        $labels = array_keys($stats);
        $revenue = array_values(ArrayHelper::getColumn($stats, 'revenue'));
        $orderCount = array_values(ArrayHelper::getColumn($stats, 'orderCount'));

        return $view->renderTemplate('commerce/_components/widgets/Orders/revenue/body',
            compact(
                'stats',
                'timeFrame',
                'namespaceId',
                'labels',
                'revenue',
                'orderCount'
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

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/Orders/revenue/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
