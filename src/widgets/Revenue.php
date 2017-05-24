<?php

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;

class Revenue extends Widget
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function isSelectable(): bool
    {
        // This widget is only available to users that can manage orders
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('commerce', 'Revenue');
    }

    /**
     * @inheritDoc IWidget::getIconPath()
     *
     * @return string
     */
    public function getIconPath()
    {
        return craft()->path->getPluginsPath().'commerce/resources/icon-mask.svg';
    }

    /**
     * @inheritDoc IWidget::getBodyHtml()
     *
     * @return string|false
     */
    public function getBodyHtml()
    {
        $dateRange = false;

        $settings = $this->getSettings();

        $dateRanges = ChartHelper::getDateRanges();

        if (!empty($dateRanges[$settings->dateRange])) {
            $dateRange = $dateRanges[$settings->dateRange]['label'];
        }

        $options = [
            'dateRange' => $settings->dateRange
        ];

        Craft::$app->getView()->includeCssResource('commerce/CommerceRevenueWidget.css');
        Craft::$app->getView()->includeJsResource('commerce/js/CommerceRevenueWidget.js');

        $js = 'new Craft.Commerce.RevenueWidget('.$this->model->id.', '.JsonHelper::encode($options).');';

        Craft::$app->getView()->includeJs($js);

        return '<div class="chart hidden"></div>';
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        $dateRanges = ChartHelper::getDateRanges();

        $dateRangeOptions = [];

        foreach ($dateRanges as $key => $dateRange) {
            $dateRangeOptions[] = [
                'value' => $key,
                'label' => $dateRange['label']
            ];
        }

        return Craft::$app->getView()->render('commerce/_components/widgets/Revenue/settings', [
            'settings' => $this->getSettings(),
            'dateRangeOptions' => $dateRangeOptions
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseSavableComponentType::defineSettings()
     *
     * @return array
     */
    protected function defineSettings()
    {
        return [
            'dateRange' => AttributeType::String,
        ];
    }
}
