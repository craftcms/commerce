<?php
namespace craft\commerce\web\assets\revenuewidget;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle for the Revenue widget
 */
class RevenueWidgetAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__.'/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css[] = 'css/RevenueWidget.css';

        $this->js[] = 'js/RevenueWidget.js';

        parent::init();
    }
}
