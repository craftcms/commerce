<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\errors\InvalidElementException;
use craft\records\Element;
use craft\test\fixtures\elements\ElementFixture;
use yii\db\Exception;

/**
 * Class OrdersFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrdersFixture extends ElementFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/orders.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Order::class;

    /**
     * @inheritdoc
     */
    public $depends = [
        ProductFixture::class,
        CustomersAddressesFixture::class,
        OrderStatusesFixture::class,
    ];

    /**
     * @inheritdoc
     */
    public function load()
    {
        $this->data = [];

        foreach ($this->getData() as $alias => $data) {
            /* @var Order $element */
            $element = $this->getElement($data) ?: new $this->modelClass;

            // If they want to add a date deleted. Store it but dont set that as an element property
            $dateDeleted = null;

            if (isset($data['dateDeleted'])) {
                $dateDeleted = $data['dateDeleted'];
                unset($data['dateDeleted']);
            }

            // Set the field layout
            if (isset($data['fieldLayoutType'])) {
                $fieldLayoutType = $data['fieldLayoutType'];
                unset($data['fieldLayoutType']);

                $fieldLayout = Craft::$app->getFields()->getLayoutByType($fieldLayoutType);
                if ($fieldLayout) {
                    $element->fieldLayoutId = $fieldLayout->id;
                } else {
                    codecept_debug("Field layout with type: $fieldLayoutType could not be found");
                }
            }

            foreach ($data as $handle => $value) {
                if (!in_array($handle, ['_lineItems', '_markAsComplete'])) {
                    $element->$handle = $value;
                }
            }


            // Save to get an ID
            if (!Craft::$app->getElements()->saveElement($element)) {
                throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
            }

            $this->_setLineItems($element, $data['_lineItems'] ?? []);

            // Resave after extra data
            if (!Craft::$app->getElements()->saveElement($element)) {
                throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
            }

            // Complete order if required
            if ($data['_markAsComplete']) {
                $element->markAsComplete();
            }

            // Add it here
            if ($dateDeleted) {
                $elementRecord = Element::find()
                    ->where(['id' => $element->id])
                    ->one();

                $elementRecord->dateDeleted = $dateDeleted;

                if (!$elementRecord->save()) {
                    throw new Exception('Unable to set element as deleted');
                }
            } else {
                Craft::$app->getSearch()->indexElementAttributes($element);
            }

            $this->data[$alias] = array_merge($data, ['id' => $element->id]);
        }
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidElementException
     * @throws Throwable
     */
    public function unload()
    {
        if ($this->unload) {
            foreach ($this->getData() as $data) {
                $element = $this->getElement($data);

                // TODO check if we need to delete anything manually.

                if ($element && !Craft::$app->getElements()->deleteElement($element, true)) {
                    throw new InvalidElementException($element, 'Unable to delete element.');
                }

            }

            $this->data = [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function isPrimaryKey(string $key): bool
    {
        return parent::isPrimaryKey($key) || in_array($key, ['title']);
    }

    private function _setLineItems(Order $order, $lineItems)
    {
        if (empty($lineItems)) {
            return;
        }

        $orderLineItems = [];
        foreach ($lineItems as $lineItem) {
            $orderLineItems[] = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $lineItem['purchasbleId'], $lineItem['options'], $lineItem['qty'], $lineItem['note']);
        }

        $order->setLineItems($orderLineItems);

    }
}
