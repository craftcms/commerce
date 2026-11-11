<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\DigitalProduct\Elements\License;

/**
 * Everything is allowed for users with the “Manage licenses” permission.
 */
class LicensePolicy extends ElementPolicy
{
    public function view(CraftUser $user, License $license): bool
    {
        return $user->can('commerce-manageLicenses');
    }

    public function save(CraftUser $user, License $license): bool
    {
        return $user->can('commerce-manageLicenses');
    }

    public function delete(CraftUser $user, License $license): bool
    {
        return $user->can('commerce-manageLicenses');
    }

    public function createDrafts(CraftUser $user, License $license): bool
    {
        return $user->can('commerce-manageLicenses');
    }
}
