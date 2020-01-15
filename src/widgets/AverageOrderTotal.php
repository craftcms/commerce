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
use craft\commerce\stats\AverageOrderTotal as AverageOrderTotalStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\StringHelper;

/**
 * Average Order Total widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class AverageOrderTotal extends Widget
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
        return Plugin::t( 'Average Order Total');
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
        $stat = new AverageOrderTotalStat(AverageOrderTotalStat::DATE_RANGE_PASTYEAR);
        $number = $stat->get();
        $timeFrame = $stat->getDateRangeWording();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/Orders/average/body', compact('number', 'timeFrame'));
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
        $id = 'average-order-total' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/Orders/average/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
