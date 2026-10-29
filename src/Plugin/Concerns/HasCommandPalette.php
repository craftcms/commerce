<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Cp\CommandPalette\PageRegistry;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\CommandPalette\CommercePages;
use CraftCms\Commerce\Plugin;

/**
 * @mixin Plugin
 */
trait HasCommandPalette
{
    private function registerCommandPalette(): void
    {
        app(PageRegistry::class)->register(
            self::HANDLE,
            fn(CraftUser $user, CommercePages $pages) => $pages->all($user),
        );
    }
}
