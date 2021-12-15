<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\chartjs;

use craft\web\AssetBundle;

/**
 * Asset bundle for the Chart JS
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ChartJsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'Chart.bundle.min.js',
            'moment-with-locales.min.js',
            'chartjs-adapter-moment.min.js',
        ];

        parent::init();
    }
}
