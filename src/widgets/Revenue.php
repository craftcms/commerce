<?php

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\web\assets\revenuewidget\RevenueWidgetAsset;
use craft\helpers\ChartHelper;
use craft\helpers\Json;

/**
 * Class Revenue
 *
 * @property string|false $bodyHtml
 * @property string       $name
 * @property string       $settingsHtml
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class Revenue extends Widget
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $dateRange;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        // This widget is only available to users that can manage orders
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return Craft::t('commerce', 'Revenue');
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
        $options = [
            'dateRange' => $this->dateRange
        ];

        $view = Craft::$app->getView();
        $view->registerAssetBundle(RevenueWidgetAsset::class);

        $js = 'new Craft.Commerce.RevenueWidget('.$this->id.', '.Json::encode($options).');';

        $view->registerJs($js);

        return '<div class="chart hidden"></div>';
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $dateRanges = ChartHelper::dateRanges();

        $dateRangeOptions = [];

        foreach ($dateRanges as $key => $dateRange) {
            $dateRangeOptions[] = [
                'value' => $key,
                'label' => $dateRange['label']
            ];
        }

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/Revenue/settings', [
            'widget' => $this,
            'dateRangeOptions' => $dateRangeOptions
        ]);
    }
}
