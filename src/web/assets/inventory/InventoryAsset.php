<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\inventory;

use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\htmx\HtmxAsset;
use craft\web\View;

/**
 * Asset bundle for Inventory Assets
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class InventoryAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            HtmxAsset::class,
            AdminTableAsset::class,
        ];

        $this->css[] = 'css/inventory.css';

        $this->js[] = 'inventory.js';

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('commerce', [
                'Item',
                'No inventory found.',
                'Search inventory',
                'No inventory found.',
                'Reserved',
                'Damaged',
                'Safety',
                'Quality Control',
                'Committed',
                'Available',
                'On Hand',
                'Incoming',
            ]);
        }
    }
}
