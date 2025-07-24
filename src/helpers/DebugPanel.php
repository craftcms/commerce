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
use craft\helpers\ArrayHelper;
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
     * @param object $model
     * @param string|null $name Name of the tab to be displayed.
     * @param bool $prepend Whether to prepend the content tab.
     * @return void
     */
    public static function prependOrAppendModelTab(object $model, ?string $name = null, bool $prepend = false): void
    {
        if (!$name) {
            $classSegments = explode('\\', $model::class);
            $name = array_pop($classSegments);

            if (property_exists($model, 'id')) {
                $name .= $model->id ? sprintf(' (ID: %s)', $model->id) : ' (New)';
            }
        }

        $user = Craft::$app->getUser()->getIdentity();

        // Skip out if there is no user or `devMode` isn't enabled
        if (!$user || !Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        // Skip out if this is a CP request and the user doesn't have the preference set to `true`
        if ((Craft::$app->getRequest()->getIsCpRequest() && !$user->getPreference('enableDebugToolbarForCp'))) {
            return;
        }

        // Skip out if this is a site request and the user doesn't have the preference set to `true`
        if (!Craft::$app->getRequest()->getIsCpRequest() && !$user->getPreference('enableDebugToolbarForSite')) {
            return;
        }

        Event::on(CommercePanel::class, CommercePanel::EVENT_AFTER_DATA_PREPARE, function(CommerceDebugPanelDataEvent $event) use ($name, $model, $prepend) {
            $content = Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', compact('model'));

            ArrayHelper::prependOrAppend($event->nav, $name, $prepend);
            ArrayHelper::prependOrAppend($event->content, $content, $prepend);
        });
    }

    /**
     * @param string $attr
     * @param string|null $label
     * @return string
     */
    public static function renderModelAttributeRow(string $attr, mixed $value, ?string $label = null): string
    {
        $label = $label ?: $attr;

        if (is_string($value)) {
            if (str_contains($attr, 'html') || str_contains($attr, 'Html')) {
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
}
