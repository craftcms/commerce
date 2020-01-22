<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\statwidgets;

use Craft;
use craft\commerce\web\assets\chartjs\ChartJsAsset;
use craft\commerce\web\assets\deepmerge\DeepMergeAsset;
use craft\web\AssetBundle;

/**
 * Asset bundle for the Stat widgets
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class StatWidgetsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            DeepMergeAsset::class,
            ChartJsAsset::class,
        ];

        $this->js[] = 'js/CommerceChart.js';
        $this->css[] = 'css/statwidgets.css';

        parent::init();
    }
}
