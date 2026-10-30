<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Path;
use CraftCms\Commerce\Plugin;
use Override;

use function CraftCms\Cms\t;

/**
 * @mixin Plugin
 */
trait HasCacheOptions
{
    #[Override]
    protected function getCacheOptions(): array
    {
        return [
            'commerce-order-exports' => [
                'label' => t('Commerce order exports', category: 'commerce'),
                'action' => static function() {
                    File::cleanDirectory(app(Path::class)->runtime('commerce-order-exports'));
                },
            ],
        ];
    }
}
