<?php

declare(strict_types=1);

namespace CraftCms\Commerce\License\Events;

use CraftCms\Commerce\License\Elements\License;

/**
 * Dispatched before a license key is generated for a new license. Set `$licenseKey` to provide one.
 */
class LicenseKeyGenerating
{
    public ?string $licenseKey = null;

    public function __construct(
        public License $license,
    ) {
    }
}
