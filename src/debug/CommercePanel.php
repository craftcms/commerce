<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\debug;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\CommerceDebugPanelDataEvent;
use yii\debug\Panel;

/**
 * Debugger panel that collects and displays Commerce information.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class CommercePanel extends Panel
{
    /**
     * @event \yii\base\Event The event that is triggered after the data for the panel is prepared.
     *
     * ```php
     * use craft\commerce\debug\CommercePanel;
     * use craft\commerce\events\CommerceDebugPanelDataEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     CommercePanel::class,
     *     CommercePanel::EVENT_AFTER_DATA_PREPARE,
     *     function(CommerceDebugPanelDataEvent $event) {
     *         $event->nav[] = 'Foo';
     *         $event->content[] = '<strong>Bar</strong>';
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_DATA_PREPARE = 'afterDataPrepare';

    /**
     * @var Order|null
     */
    public ?Order $cart = null;

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Commerce';
    }

    /**
     * @inheritdoc
     */
    public function getSummary(): string
    {
        return Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/summary', [
            'panel' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getDetail(): string
    {
        return Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/detail', [
            'panel' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function save()
    {
        $nav = [];
        $content = [];

        if ($this->cart) {
            $nav[] = 'Cart (in session)';
            $cartAttributes = array_merge(array_keys($this->cart->fields()), $this->cart->extraFields());

            $content[] =
                Craft::$app->getView()->render('@craft/commerce/views/debug/commerce/model', [
                    'model' => $this->cart,
                    'attributes' => $cartAttributes,
                    'toArrayAttributes' => [
                        'billingAddress',
                        'customer',
                        'estimatedBillingAddress',
                        'estimatedShippingAddress',
                        'lineItems',
                        'shippingAddress',
                        'transactions',
                    ]
                ]);
        }

        // Trigger event allowing extra tabs to be added.
        $event = new CommerceDebugPanelDataEvent(['nav' => $nav, 'content' => $content]);
        $this->trigger(self::EVENT_AFTER_DATA_PREPARE, $event);

        return ['nav' => $event->nav, 'content' => $event->content];
    }
}