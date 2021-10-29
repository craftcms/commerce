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
     * @param object $model
     * @param string|null $name Name of the tab to be displayed.
     */
    public static function appendModelTab(object $model, ?string $name = null): void
    {
        self::_registerPanelEventListener($model, $name);
    }

    /**
     * Add a model debug tab to the debug panel.
     *
     * @param object $model
     * @param string|null $name Name of the tab to be displayed.
     */
    public static function prependModelTab(object $model, ?string $name = null): void
    {
        self::_registerPanelEventListener($model, $name, true);
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
     * @param object $model
     * @param string|null $name Name of the tab to be displayed.
     * @param bool $prepend Whether to prepend the content tab.
     */
    private static function _registerPanelEventListener(object $model, ?string $name = null, bool $prepend = false): void
    {
        if (!$name) {
            $classSegments = explode('\\', get_class($model));
            $name = array_pop($classSegments);

            if (property_exists($model, 'id')) {
                $name .= $model->id ? sprintf(' (ID: %s)', $model->id) : ' (New)';
            }
        }

        $user = Craft::$app->getUser()->getIdentity();
        $pref = Craft::$app->getRequest()->getIsCpRequest() ? 'enableDebugToolbarForCp' : 'enableDebugToolbarForSite';

        if (!Craft::$app->getRequest()->getIsCpRequest() || !$user || !$user->getPreference($pref) || !Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        Event::on(CommercePanel::class, CommercePanel::EVENT_AFTER_DATA_PREPARE, function(CommerceDebugPanelDataEvent $event) use ($name, $model, $prepend) {
            $content = Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', compact('model'));

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