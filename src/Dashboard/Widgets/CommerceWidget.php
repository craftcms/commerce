<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Dashboard\Widgets\Widget;
use Override;

use function CraftCms\Cms\currentUser;

/**
 * Base class for Commerce dashboard widgets.
 *
 * Widgets declare the permissions they require in {@see requiredPermissions()}, which are enforced both when
 * selecting the widget and when rendering it, so a widget can't be created or rendered by a user without them.
 */
abstract class CommerceWidget extends Widget
{
    /**
     * Returns the permissions the current user must have to use this widget.
     *
     * @return string[]
     */
    abstract protected static function requiredPermissions(): array;

    abstract protected function renderComponent(): ?string;

    /** @return array<string, mixed> */
    abstract protected function renderProps(): array;

    public static function hasPermission(): bool
    {
        $user = currentUser();

        foreach (static::requiredPermissions() as $permission) {
            if (!$user?->can($permission)) {
                return false;
            }
        }

        return true;
    }

    #[Override]
    public static function isSelectable(): bool
    {
        return static::hasPermission();
    }

    #[Override]
    final public function component(): ?string
    {
        return static::hasPermission() ? $this->renderComponent() : null;
    }

    #[Override]
    final public function props(): array
    {
        return static::hasPermission() ? $this->renderProps() : [];
    }
}
