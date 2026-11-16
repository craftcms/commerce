<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Widget as BaseWidget;

/**
 * Base class for Commerce dashboard widgets.
 *
 * Widgets declare the permissions they require in {@see requiredPermissions()}, which are enforced both when
 * selecting the widget and when rendering its body, so a widget can’t be created or rendered by a user without them.
 *
 * @since 5.7.9
 */
abstract class Widget extends BaseWidget
{
    /**
     * Returns the permissions the current user must have to use this widget.
     *
     * @return string[]
     */
    abstract protected static function requiredPermissions(): array;

    /**
     * Returns whether the current user has all the permissions required by this widget.
     */
    public static function hasPermission(): bool
    {
        $user = Craft::$app->getUser();

        foreach (static::requiredPermissions() as $permission) {
            if (!$user->checkPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return static::hasPermission();
    }

    /**
     * @inheritdoc
     */
    final public function getBodyHtml(): ?string
    {
        if (!static::hasPermission()) {
            return null;
        }

        return $this->renderBodyHtml();
    }

    /**
     * Returns the widget’s body HTML, once the user’s permissions have been verified.
     */
    abstract protected function renderBodyHtml(): ?string;
}
