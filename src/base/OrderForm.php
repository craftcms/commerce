<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\commerce\elements\Order;
use yii\base\InvalidConfigException;

/**
 * Order Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 *
 * @property Order|null $cart
 */
abstract class OrderForm extends Model implements OrderFormInterface
{
    private ?Order $_order = null;
    private mixed $_successMessage = null;
    private mixed $_failMessage = null;

    private array $_validateDataKeys = [
        'successMessage',
        'failMessage',
    ];

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['successMessage', 'failMessage'], 'string'];
        $rules[] = [[
            'order',
            'successMessage',
            'failMessage',
        ], 'safe'];
        $rules[] = ['order', 'required'];

        return $rules;
    }


    /**
     * @param $data
     * @param $formName
     * @param array $validateDataKeys
     * @return bool
     * @throws InvalidConfigException
     */
    public function load($data, $formName = null): bool
    {
        if (!empty($data)) {
            $scope = $formName ?? $this->formName();
            if ($scope === '') {
                $data = $this->_validateData($data);
            } elseif (isset($data[$scope])) {
                $data[$scope] = $this->_validateData($data[$scope]);
            }
        }

        return parent::load($data, $formName);
    }

    private function _validateData(array $data): array
    {
        if (empty($this->getValidateDataKeys())) {
            return $data;
        }

        foreach ($data as $key => &$datum) {
            if (!in_array($key, $this->getValidateDataKeys(), true)) {
                continue;
            }

            $datum = Craft::$app->getSecurity()->validateData($datum);
        }

        return $data;
    }

    /**
     * @return array|string[]
     */
    public function getValidateDataKeys(): array
    {
        return $this->_validateDataKeys;
    }

    /**
     * @param string|null $message
     * @return void
     */
    public function setSuccessMessage(mixed $message): void
    {
        $this->_successMessage = $message;
    }

    /**
     * @return string
     */
    public function getSuccessMessage(): string
    {
        return $this->_successMessage ?? Craft::t('commerce', 'Order updated.');
    }

    /**
     * @param string|null $message
     * @return void
     */
    public function setFailMessage(mixed $message): void
    {
        $this->_failMessage = $message;
    }

    /**
     * @return string
     */
    public function getFailMessage(): string
    {
        return $this->_failMessage ?? Craft::t('commerce', 'Unable to update order.');
    }

    /**
     * @param Order $cart
     * @return void
     */
    public function setOrder(Order $cart): void
    {
        $this->_order = $cart;
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->_order;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function formName(): string
    {
        return lcfirst(parent::formName());
    }

    /**
     * @inheritdoc
     */
    public function apply(): bool
    {
        if (!$this->getOrder()) {
            $this->addError('cart', Craft::t('commerce', 'No cart exists to update.'));
            return false;
        }
        return true;
    }
}
