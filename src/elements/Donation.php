<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\db\DonationQuery;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\Donation as DonationRecord;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use yii\base\Exception;
use yii\validators\Validator;

/**
 * Donation purchasable.
 *
 * @property-read string $priceAsCurrency
 * @property-read string $salePriceAsCurrency
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Donation extends Purchasable
{
    /**
     * @var bool Is the product available for purchase.
     */
    public bool $availableForPurchase = false;

    /**
     * @var string The SKU
     */
    private string $_sku;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => $this->_order->currency ?? Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
            'currencyAttributes' => $this->currencyAttributes(),
            'attributeCurrencyMap' => [],
        ];

        return $behaviors;
    }

    /**
     * @return string[]
     */
    public function currencyAttributes(): array
    {
        return [
            'price',
            'salePrice',
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['sku'], 'trim'];
        $rules[] = [
            ['sku'], 'required', 'when' => fn($model) =>
                /** @var self $model */
                $model->availableForPurchase && $model->enabled,
        ];

        return $rules;
    }

    /**
     * @inerhitdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPrice(): float
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'donation');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Donations');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'donations');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'donation';
    }

    /**
     * @inheritdoc
     * @return DonationQuery The newly created [[DonationQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new DonationQuery(static::class);
    }

    /**
     * Returns the product title and variants title together for variable products.
     */
    public function getDescription(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/store-settings/donation');
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): ?string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->_sku;
    }

    public function setSku(?string $value): void
    {
        $this->_sku = $value;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsShippable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getIsTaxable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem): void
    {
        $options = $lineItem->getOptions();
        if (isset($options['donationAmount'])) {
            $lineItem->price = $options['donationAmount'];
            $lineItem->salePrice = $options['donationAmount'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getLineItemRules(LineItem $lineItem): array
    {
        return [
            [
                'purchasableId',
                function($attribute, $params, Validator $validator) use ($lineItem) {
                    $options = $lineItem->getOptions();
                    if (!isset($options['donationAmount'])) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'No donation amount supplied.'));
                    }
                    if (isset($options['donationAmount']) && !is_numeric($options['donationAmount'])) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'Donation needs to be an amount.'));
                    }
                    if (isset($options['donationAmount']) && $options['donationAmount'] == 0) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'Donation can not be zero.'));
                    }
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        return $this->availableForPurchase;
    }

    /**
     * @throws Exception
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = DonationRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid donation ID: ' . $this->id);
            }
        } else {
            $record = new DonationRecord();
            $record->id = $this->id;
        }

        $record->sku = $this->sku;
        $record->availableForPurchase = $this->availableForPurchase;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        $record->save(false);

        parent::afterSave($isNew);
    }
}
