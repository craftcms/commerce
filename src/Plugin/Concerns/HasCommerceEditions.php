<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Edition;

trait HasCommerceEditions
{
    public const int EDITION_PRO_STORE_LIMIT = 5;

    public const string EDITION_PRO = 'pro';

    public const string EDITION_ENTERPRISE = 'enterprise';

    public Edition $minCmsEdition = Edition::Pro;

    public static function editions(): array
    {
        return [
            self::EDITION_PRO,
            self::EDITION_ENTERPRISE,
        ];
    }
}
