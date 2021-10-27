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
use yii\base\Event;

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
    public static function addModelTab(string $name, object $model, array $toArrayAttributes = [], array $attributes = []): void
    {
        $user = Craft::$app->getUser()->getIdentity();
        $pref = Craft::$app->getRequest()->getIsCpRequest() ? 'enableDebugToolbarForCp' : 'enableDebugToolbarForSite';

        if (!Craft::$app->getRequest()->getIsCpRequest() || !$user || !$user->getPreference($pref) || !Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        Event::on(CommercePanel::class, CommercePanel::EVENT_AFTER_DATA_PREPARE, function(CommerceDebugPanelDataEvent $event) use ($name, $model, $toArrayAttributes, $attributes) {
            $event->nav[] = $name;
            $event->content[] = Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', [
                'model' => $model,
                'attributes' => !empty($attributes) ? $attributes : $model->getAttributes(),
                'toArrayAttributes' => $toArrayAttributes,
            ]);
        });
    }
}