<?php

declare(strict_types=1);

use CraftCms\Cms\User\Contracts\CraftUser;

/** @param list<string> $permissions */
function paletteUser(array $permissions, bool $admin = false): CraftUser
{
    $user = Mockery::mock(CraftUser::class);
    $user->shouldReceive('isAdmin')->andReturn($admin);
    $user->shouldReceive('can')->andReturnUsing(fn(string $permission) => $admin || in_array($permission, $permissions, true));

    return $user;
}
