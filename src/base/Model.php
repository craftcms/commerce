<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\Model as BaseModel;
use craft\commerce\Plugin;
use craft\helpers\StringHelper;

/**
 * Class Model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Model extends BaseModel
{

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (StringHelper::endsWithAny($name, ['AsCurrency'], false)) {
            $attributeName = StringHelper::removeRight($name, 'AsCurrency');
            if (in_array($attributeName, $this->currencyAttributes(), false)) {
                $amount = parent::__get($attributeName);
                \Craft::$app->getFormatter()->asCurrency($amount, $this->getCurrency(), [], [], true);
            }
        }

        return parent::__get($name);
    }

    public function fields()
    {
        $fields = parent::fields();

        foreach ($this->currencyAttributes() as $attribute) {
            $fields[] = $attribute . 'AsCurrency';
        }

        return $fields;
    }

    /**
     * @return array
     */
    public function currencyAttributes(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getCurrency(): string
    {
        return Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
    }
}
