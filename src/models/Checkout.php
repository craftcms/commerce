<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Model;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\elements\conditions\orders\CheckoutOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Checkout as CheckoutRecord;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use Illuminate\Support\Collection;

/**
 * Checkout model.
 *
 * A checkout is a configuration for the hosted checkout: how it looks, where it sends customers, which stores and
 * gateways it works with, and which orders it will take payment for.
 *
 * @property CheckoutOrderCondition $orderCondition
 * @property int[] $storeIds
 * @property int[] $gatewayIds
 * @property-read Collection<Store> $stores
 * @property-read Collection<GatewayInterface> $gateways
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Checkout extends Model
{
    public const COLOR_SCHEME_LIGHT = 'light';
    public const COLOR_SCHEME_DARK = 'dark';
    public const COLOR_SCHEME_AUTO = 'auto';

    public const CORNER_STYLE_SHARP = 'sharp';
    public const CORNER_STYLE_ROUNDED = 'rounded';
    public const CORNER_STYLE_PILL = 'pill';

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Whether the checkout can be used
     */
    public bool $enabled = true;

    /**
     * @var bool Whether this is the checkout used when none is specified
     */
    public bool $isDefault = false;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var string|null The accent color, as a hex value
     */
    public ?string $brandColor = null;

    /**
     * @var string One of the `COLOR_SCHEME_*` values
     */
    public string $colorScheme = self::COLOR_SCHEME_LIGHT;

    /**
     * @var string One of the `CORNER_STYLE_*` values
     */
    public string $cornerStyle = self::CORNER_STYLE_ROUNDED;

    /**
     * @var string|null The URL of a logo, shown in place of the site name
     */
    public ?string $logoUrl = null;

    /**
     * @var string|null Where customers are sent after paying. `{number}`, `{shortNumber}` and `{id}` are replaced with the order’s.
     */
    public ?string $returnUrl = null;

    /**
     * @var string|null Where the “Continue shopping” links go
     */
    public ?string $continueShoppingUrl = null;

    /**
     * @var bool Whether customers can pay part of the total now. Stores that don’t allow partial payments override this.
     */
    public bool $allowPartialPayment = false;

    /**
     * @var int[] The percentages of the total customers can choose to pay
     */
    public array $partialPaymentPercentages = [];

    /**
     * @var bool|null Whether a billing address is required. `null` follows the store.
     */
    public ?bool $requireBillingAddress = null;

    /**
     * @var string|null Shown to customers whose order doesn’t match the order condition
     */
    public ?string $orderConditionMessage = null;

    /**
     * @var DateTime|null When the checkout was created
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null When the checkout was last updated
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    private ?CheckoutOrderCondition $_orderCondition = null;

    /**
     * @var int[]
     */
    private array $_storeIds = [];

    /**
     * @var int[]
     */
    private array $_gatewayIds = [];

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle', 'storeIds'], 'required'];
        $rules[] = [['name', 'handle', 'brandColor', 'logoUrl', 'returnUrl', 'continueShoppingUrl', 'orderConditionMessage'], 'string', 'max' => 255];
        $rules[] = [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']];
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => CheckoutRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'];
        $rules[] = [['brandColor'], 'match', 'pattern' => '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', 'skipOnEmpty' => true, 'message' => Craft::t('commerce', 'Enter a hex color, such as #4f46e5.')];
        $rules[] = [['colorScheme'], 'in', 'range' => array_keys(self::getColorSchemeOptions())];
        $rules[] = [['cornerStyle'], 'in', 'range' => array_keys(self::getCornerStyleOptions())];
        $rules[] = [['enabled', 'isDefault', 'allowPartialPayment'], 'boolean'];
        $rules[] = [['partialPaymentPercentages'], 'validatePartialPaymentPercentages'];
        $rules[] = [['returnUrl', 'continueShoppingUrl'], 'validateUrlTemplate'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'storeIds' => Craft::t('commerce', 'Stores'),
            'returnUrl' => Craft::t('commerce', 'Return URL'),
            'continueShoppingUrl' => Craft::t('commerce', 'Continue Shopping URL'),
            'partialPaymentPercentages' => Craft::t('commerce', 'Partial Payment Percentages'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getColorSchemeOptions(): array
    {
        return [
            self::COLOR_SCHEME_LIGHT => Craft::t('commerce', 'Light'),
            self::COLOR_SCHEME_DARK => Craft::t('commerce', 'Dark'),
            self::COLOR_SCHEME_AUTO => Craft::t('commerce', 'Match the customer’s device'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getCornerStyleOptions(): array
    {
        return [
            self::CORNER_STYLE_SHARP => Craft::t('commerce', 'Sharp'),
            self::CORNER_STYLE_ROUNDED => Craft::t('commerce', 'Rounded'),
            self::CORNER_STYLE_PILL => Craft::t('commerce', 'Pill'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        // Color fields post the hex value without its “#”
        if ($this->brandColor !== null && preg_match('/^[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $this->brandColor)) {
            $this->brandColor = '#' . $this->brandColor;
        }

        return parent::beforeValidate();
    }

    /**
     * @param string $attribute
     */
    public function validatePartialPaymentPercentages(string $attribute): void
    {
        if (!$this->allowPartialPayment) {
            return;
        }

        if (empty($this->partialPaymentPercentages)) {
            $this->addError($attribute, Craft::t('commerce', 'Add at least one percentage customers can pay up front.'));
            return;
        }

        foreach ($this->partialPaymentPercentages as $percentage) {
            if ($percentage < 1 || $percentage > 99) {
                $this->addError($attribute, Craft::t('commerce', 'Percentages must be between 1 and 99.'));
                return;
            }
        }
    }

    /**
     * @param string $attribute
     */
    public function validateUrlTemplate(string $attribute): void
    {
        $value = (string)$this->$attribute;

        if ($value === '') {
            return;
        }

        // The tokens are replaced when the URL is used, so check it as it would be then
        if (preg_match('/\s/', $value)) {
            $this->addError($attribute, Craft::t('commerce', 'URLs can’t contain spaces.'));
        }
    }

    /**
     * Sets the partial payment percentages from an array, or from comma separated text.
     *
     * @param array|string|null $percentages
     */
    public function setPartialPaymentPercentages(array|string|null $percentages): void
    {
        if (is_string($percentages)) {
            $percentages = Json::decodeIfJson($percentages);
            $percentages = is_array($percentages) ? $percentages : explode(',', (string)$percentages);
        }

        $percentages = array_map(fn($p) => (int)trim((string)$p), $percentages ?? []);
        $percentages = array_values(array_unique(array_filter($percentages)));
        sort($percentages);

        $this->partialPaymentPercentages = $percentages;
    }

    /**
     * @return int[]
     */
    public function getStoreIds(): array
    {
        return $this->_storeIds;
    }

    /**
     * @param array<int|string> $ids
     */
    public function setStoreIds(array $ids): void
    {
        $this->_storeIds = array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * @return Collection<Store>
     */
    public function getStores(): Collection
    {
        return Plugin::getInstance()->getStores()->getAllStores()
            ->filter(fn(Store $store) => in_array($store->id, $this->_storeIds, true))
            ->values();
    }

    /**
     * @return int[]
     */
    public function getGatewayIds(): array
    {
        return $this->_gatewayIds;
    }

    /**
     * @param array<int|string> $ids
     */
    public function setGatewayIds(array $ids): void
    {
        $this->_gatewayIds = array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * @return Collection<GatewayInterface>
     */
    public function getGateways(): Collection
    {
        return Plugin::getInstance()->getGateways()->getAllGateways()
            ->filter(fn(GatewayInterface $gateway) => in_array($gateway->id, $this->_gatewayIds, true))
            ->values();
    }

    /**
     * Returns whether the checkout works with a store.
     */
    public function allowsStore(Store|HasStoreInterface|int $store): bool
    {
        $storeId = is_int($store) ? $store : ($store instanceof Store ? $store->id : $store->getStore()->id);

        return in_array($storeId, $this->_storeIds, true);
    }

    /**
     * Returns whether customers can pay with a gateway. A checkout with no gateways selected allows all of them.
     */
    public function allowsGateway(GatewayInterface|int $gateway): bool
    {
        $gatewayId = is_int($gateway) ? $gateway : $gateway->id;

        return empty($this->_gatewayIds) || in_array($gatewayId, $this->_gatewayIds, true);
    }

    /**
     * Returns whether customers can pay part of an order now. The store has the final say.
     */
    public function allowsPartialPayment(Store $store): bool
    {
        return $this->allowPartialPayment && !empty($this->partialPaymentPercentages) && $store->getAllowPartialPaymentOnCheckout();
    }

    /**
     * Returns whether a billing address is required for orders in a store.
     */
    public function getRequireBillingAddress(Store $store): bool
    {
        return $this->requireBillingAddress ?? (bool)$store->getRequireBillingAddressAtCheckout();
    }

    /**
     * @return CheckoutOrderCondition
     */
    public function getOrderCondition(): CheckoutOrderCondition
    {
        $condition = $this->_orderCondition ?? new CheckoutOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';

        return $condition;
    }

    /**
     * @param CheckoutOrderCondition|string|array|null $condition
     */
    public function setOrderCondition(CheckoutOrderCondition|string|array|null $condition): void
    {
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof CheckoutOrderCondition) {
            $condition['class'] = CheckoutOrderCondition::class;
            /** @var CheckoutOrderCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }

        $condition->forProjectConfig = true;
        $this->_orderCondition = $condition;
    }

    /**
     * Returns whether the checkout has any order condition rules.
     */
    public function hasOrderCondition(): bool
    {
        return $this->getOrderCondition()->getConditionRules() !== [];
    }

    /**
     * Returns why an order can’t be paid for through this checkout, or `null` if it can.
     */
    public function getBlockedReason(Order $order): ?string
    {
        if (!$this->enabled) {
            return Craft::t('commerce', 'This checkout isn’t available.');
        }

        if (!$this->allowsStore($order->getStore())) {
            return Craft::t('commerce', 'This checkout isn’t available for this store.');
        }

        if ($this->hasOrderCondition() && !$this->getOrderCondition()->matchElement($order)) {
            return $this->orderConditionMessage ?: Craft::t('commerce', 'Your order can’t be checked out right now.');
        }

        return null;
    }

    /**
     * Returns the URL to send the customer to after paying, or `null` to use the order’s own return URL.
     */
    public function getReturnUrlForOrder(Order $order): ?string
    {
        if (!$this->returnUrl) {
            return null;
        }

        $url = strtr($this->returnUrl, [
            '{number}' => rawurlencode((string)$order->number),
            '{shortNumber}' => rawurlencode((string)$order->getShortNumber()),
            '{id}' => (string)$order->id,
        ]);

        return UrlHelper::isFullUrl($url) ? $url : UrlHelper::siteUrl($url);
    }

    /**
     * Returns the URL the “Continue shopping” links go to.
     */
    public function getContinueShoppingUrlForSite(): string
    {
        $url = $this->continueShoppingUrl ?: '/';

        return UrlHelper::isFullUrl($url) ? $url : UrlHelper::siteUrl($url);
    }

    /**
     * Returns the URL of the checkout’s settings in the control panel.
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/checkouts/' . $this->id);
    }

    /**
     * Returns the config used by project config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $stores = Plugin::getInstance()->getStores();
        $gateways = Plugin::getInstance()->getGateways();

        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'enabled' => $this->enabled,
            'isDefault' => $this->isDefault,
            'sortOrder' => $this->sortOrder ?? 99,
            'brandColor' => $this->brandColor ?: null,
            'colorScheme' => $this->colorScheme,
            'cornerStyle' => $this->cornerStyle,
            'logoUrl' => $this->logoUrl ?: null,
            'returnUrl' => $this->returnUrl ?: null,
            'continueShoppingUrl' => $this->continueShoppingUrl ?: null,
            'allowPartialPayment' => $this->allowPartialPayment,
            'partialPaymentPercentages' => $this->partialPaymentPercentages,
            'requireBillingAddress' => $this->requireBillingAddress,
            'orderCondition' => $this->hasOrderCondition() ? $this->getOrderCondition()->getConfig() : null,
            'orderConditionMessage' => $this->orderConditionMessage ?: null,
            'stores' => array_values(array_filter(array_map(fn(int $id) => $stores->getStoreById($id)?->uid, $this->_storeIds))),
            'gateways' => array_values(array_filter(array_map(fn(int $id) => $gateways->getGatewayById($id)?->uid, $this->_gatewayIds))),
        ];
    }
}
