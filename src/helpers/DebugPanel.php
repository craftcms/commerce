<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\debug\CommercePanel;
use craft\commerce\events\CommerceDebugPanelDataEvent;
use craft\helpers\Html;
use yii\base\Event;
use yii\helpers\VarDumper;

/**
 * Class DebugPanel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class DebugPanel
{
    /**
     * Add a model debug tab to the debug panel.
     *
     * @param string $name Name of the tab to be displayed.
     * @param object $model
     * @param array $toArrayAttributes Which attributes to call `toArray` on.
     * @param array $attributes List of attributes to display, if empty the models `fields()` method will be user.
     */
    public static function appendModelTab(string $name, object $model, array $toArrayAttributes = [], array $attributes = []): void
    {
        self::_registerPanelEventListener($name, $model, $toArrayAttributes, $attributes);
    }

    /**
     * Add a model debug tab to the debug panel.
     *
     * @param string $name Name of the tab to be displayed.
     * @param object $model
     * @param array $toArrayAttributes Which attributes to call `toArray` on.
     * @param array $attributes List of attributes to display, if empty the models `fields()` method will be user.
     */
    public static function prependModelTab(string $name, object $model, array $toArrayAttributes = [], array $attributes = []): void
    {
        self::_registerPanelEventListener($name, $model, $toArrayAttributes, $attributes, true);
    }

    /**
     * @param string $attr
     * @param mixed $value
     * @param string|null $label
     * @return string
     */
    public static function renderModelAttributeRow(string $attr, $value, ?string $label = null): string
    {
        $label = $label ?: $attr;

        if (is_string($value)) {
            if (strpos($attr, 'html') !== -1) {
                $output = Html::encode($value);
            } else {
                $output = $value;
            }
        } else {
            $output = VarDumper::dumpAsString($value);
        }

        return Html::tag('tr',
            Html::tag('th', $label)
            . Html::tag('td', Html::tag('code', $output))
        );
    }

    /**
     * @param string $name Name of the tab to be displayed.
     * @param object $model
     * @param array $toArrayAttributes Which attributes to call `toArray` on.
     * @param array $attributes List of attributes to display, if empty the models `fields()` method will be user.
     * @param bool $prepend Whether to prepend the content tab.
     */
    private static function _registerPanelEventListener(string $name, object $model, array $toArrayAttributes = [], array $attributes = [], bool $prepend = false): void
    {
        $user = Craft::$app->getUser()->getIdentity();
        $pref = Craft::$app->getRequest()->getIsCpRequest() ? 'enableDebugToolbarForCp' : 'enableDebugToolbarForSite';

        if (!Craft::$app->getRequest()->getIsCpRequest() || !$user || !$user->getPreference($pref) || !Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        Event::on(CommercePanel::class, CommercePanel::EVENT_AFTER_DATA_PREPARE, function(CommerceDebugPanelDataEvent $event) use ($name, $model, $toArrayAttributes, $attributes, $prepend) {
            $content = Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', [
                'model' => $model,
            ]);

            if ($prepend) {
                array_unshift($event->nav, $name);
                array_unshift($event->content, $content);
            } else {
                $event->nav[] = $name;
                $event->content[] = $content;
            }
        });
    }
}