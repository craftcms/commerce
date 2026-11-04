<?php

declare(strict_types=1);

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Commerce\Store\Data\Store;

// Commerce registers these macros when the plugin boots (see HasCommerceMacros), which only
// happens when it's installed. Registering them here lets PHPStan resolve them in any environment.
Site::macro('getStore', fn(): ?Store => null);
