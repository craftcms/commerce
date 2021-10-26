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
     * @param string $name
     * @param object $model
     * @param array $attributes
     */
    public static function addModelTab(string $name, object $model, array $attributes = []): void
    {
        Event::on(CommercePanel::class, CommercePanel::EVENT_AFTER_DATA_PREPARE, function(CommerceDebugPanelDataEvent $event) use ($name, $model, $attributes) {
            $event->nav[] = $name;
            $event->content[] = Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', [
                'model' => $model,
                'attributes' => !empty($attributes) ? $attributes : $model->getAttributes(),
            ]);
        });
    }
}