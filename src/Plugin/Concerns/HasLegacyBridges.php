<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use craft\base\Event as YiiEvent;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Yii2Adapter\Yii2ServiceProvider;
use Illuminate\Support\Facades\Event;

use function CraftCms\Cms\t;

/**
 * @mixin Plugin
 */
trait HasLegacyBridges
{
    /**
     * Whether the project has installed `craftcms/yii2-adapter`, which the legacy `craft\commerce\*` classes require.
     */
    private static function hasYii2Adapter(): bool
    {
        return class_exists(Yii2ServiceProvider::class);
    }

    private function registerCKEditorLinkOptions(): void
    {
        if (!self::hasYii2Adapter() || !class_exists(CKEditorField::class)) {
            return;
        }

        $ckEditorPlugin = Plugins::getPlugin('ckeditor');
        if (!$ckEditorPlugin || version_compare($ckEditorPlugin->version, '3.0', '<')) {
            return;
        }

        /** @phpstan-ignore-next-line argument.type, class.notFound (craft\ckeditor\Field/DefineLinkOptionsEvent belong to the optional, third-party craftcms/ckeditor plugin, not a commerce dependency — unresolvable to static analysis when it isn't installed) */
        YiiEvent::on(CKEditorField::class, CKEditorField::EVENT_DEFINE_LINK_OPTIONS, static function(DefineLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $sites = Sites::getAllSites();

            foreach (app(ProductTypes::class)->getAllProductTypes() as $productType) {
                foreach ($sites as $site) {
                    $productTypeSettings = $productType->getSiteSettings();
                    if (isset($productTypeSettings[$site->id]) && $productTypeSettings[$site->id]->hasUrls) {
                        $productSources[] = 'productType:' . $productType->uid;
                    }
                }
            }

            $productSources = array_unique($productSources);

            if ($productSources) {
                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a product', category: 'commerce'),
                    'elementType' => Product::class,
                    'refHandle' => Product::refHandle(),
                    'sources' => $productSources,
                ];

                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a variant', category: 'commerce'),
                    'elementType' => Variant::class,
                    'refHandle' => Variant::refHandle(),
                    'sources' => $productSources,
                ];
            }
        });
    }
}
