<?php

declare(strict_types=1);

namespace CraftCms\Commerce\License;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Events\LicenseKeyGenerating;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\Commerce\Order\Events\OrderPaid;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Issues licenses for purchased variants that have “Issues License” turned on, and owns the license field layout.
 *
 * Sites that used the Digital Products plugin keep their data in the plugin’s tables until the plugin’s
 * `digital-products:migrate-to-commerce` command moves it into Commerce. Until then, Commerce hides its license
 * features, so the plugin can keep running alongside Commerce.
 */
#[Singleton]
class Licenses
{
    public const string PLUGIN_HANDLE = 'digital-products';

    public const string FIELD_LAYOUT_CONFIG_KEY = 'commerce.licenses.fieldLayouts';

    /**
     * The line item option that names a license to renew. Purchasing the line item extends that license instead
     * of issuing a new one.
     */
    public const string RENEW_LICENSE_KEY_OPTION = 'renewLicenseKey';

    /**
     * The Digital Products plugin’s tables, in the order their rows can be deleted.
     */
    public const array PLUGIN_TABLES = [
        'digitalproducts_licenses',
        'digitalproducts_products',
        'digitalproducts_producttypes_sites',
        'digitalproducts_producttypes',
    ];

    private const string CACHE_KEY = 'commerce.licenses.hasPendingPluginData';

    private ?bool $hasPendingPluginData = null;

    /**
     * Whether Commerce’s license features are available.
     */
    public function isEnabled(): bool
    {
        return !$this->hasPendingPluginData();
    }

    /**
     * Whether any of the Digital Products plugin’s tables still have rows that haven’t been migrated into Commerce.
     */
    public function hasPendingPluginData(): bool
    {
        if ($this->hasPendingPluginData !== null) {
            return $this->hasPendingPluginData;
        }

        // While the plugin is installed it can add rows at any time, so only cache the result when it isn't.
        if (Plugins::isPluginInstalled(self::PLUGIN_HANDLE)) {
            return $this->hasPendingPluginData = $this->pluginTablesHaveRows();
        }

        return $this->hasPendingPluginData = Cache::rememberForever(self::CACHE_KEY, fn() => $this->pluginTablesHaveRows());
    }

    /**
     * Clears the cached {@see hasPendingPluginData()} result, after the plugin’s data has been migrated.
     */
    public function reset(): void
    {
        $this->hasPendingPluginData = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function pluginTablesHaveRows(): bool
    {
        foreach (self::PLUGIN_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getFieldLayout(): FieldLayout
    {
        return Fields::getLayoutByType(License::class);
    }

    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();

        if (empty($data) || empty($config = reset($data))) {
            Fields::deleteLayoutsByType(License::class);
            return;
        }

        $layout = FieldLayout::createFromConfig($config);
        $layout->id = $this->getFieldLayout()->id;
        $layout->type = License::class;
        $layout->uid = key($data);
        Fields::saveLayout($layout, false);
    }

    public function handleDeletedFieldLayout(): void
    {
        Fields::deleteLayoutsByType(License::class);
    }

    /**
     * Generates a unique license key for a new license. A `LicenseKeyGenerating` listener may provide the key instead.
     */
    public function generateLicenseKey(License $license): string
    {
        event($event = new LicenseKeyGenerating($license));

        if ($event->licenseKey !== null) {
            return $event->licenseKey;
        }

        $settings = app(Plugin::class)->getSettings();
        $alphabet = $settings->licenseKeyCharacters;
        $maxIndex = strlen($alphabet) - 1;

        do {
            $licenseKey = '';
            for ($i = 0; $i < $settings->licenseKeyLength; $i++) {
                $licenseKey .= $alphabet[random_int(0, $maxIndex)];
            }
        } while (!$this->isLicenseKeyUnique($licenseKey));

        return $licenseKey;
    }

    public function isLicenseKeyUnique(string $licenseKey): bool
    {
        return !License::find()
            ->licenseKey($licenseKey)
            ->status(null)
            ->exists();
    }

    /**
     * Parses a license duration, returning null unless it's a positive ISO 8601 duration such as `P1Y`.
     */
    public static function parseDuration(?string $duration): ?DateInterval
    {
        if (!$duration || !str_starts_with($duration, 'P')) {
            return null;
        }

        try {
            $interval = new DateInterval($duration);
        } catch (Exception) {
            return null;
        }

        $now = new DateTimeImmutable();

        return $now->add($interval) > $now ? $interval : null;
    }

    /**
     * Issues one license per unit of each licensed variant in the order. A line item with the
     * {@see RENEW_LICENSE_KEY_OPTION} option extends that license instead, when the order’s customer owns it.
     */
    public function licenseOrder(Order $order): void
    {
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable instanceof Variant || !$purchasable->issuesLicenses()) {
                continue;
            }

            $renewLicenseKey = $lineItem->getOptions()[self::RENEW_LICENSE_KEY_OPTION] ?? null;

            if ($renewLicenseKey && ($license = $this->getRenewableLicense((string)$renewLicenseKey, $purchasable, $order->getCustomer()))) {
                $this->renewLicense($license, $purchasable);
                continue;
            }

            for ($i = 0; $i < $lineItem->qty; $i++) {
                $this->licenseVariantByOrder($purchasable, $order);
            }
        }
    }

    /**
     * Issues a license for a variant to the order's customer.
     */
    public function licenseVariantByOrder(Variant $variant, Order $order): bool
    {
        $customer = $order->getCustomer();

        if (!$customer) {
            return false;
        }

        $license = new License();
        $license->variantId = $variant->id;
        $license->orderId = $order->id;
        $license->ownerEmail = $customer->email;
        $license->ownerName = $customer->getName();
        $license->userId = $customer->id;
        $license->expiryDate = $variant->getLicenseExpiryDate();

        return Elements::saveElement($license);
    }

    /**
     * Returns the license with the given key if it was issued for the variant and belongs to the customer.
     */
    public function getRenewableLicense(string $licenseKey, Variant $variant, ?User $customer): ?License
    {
        /** @var License|null $license */
        $license = License::find()->licenseKey($licenseKey)->status(null)->one();

        if (!$license || $license->variantId !== $variant->id || !$customer) {
            return null;
        }

        $ownedByCustomer = $license->userId
            ? $license->userId === $customer->id
            : strcasecmp((string)$license->ownerEmail, (string)$customer->email) === 0;

        return $ownedByCustomer ? $license : null;
    }

    /**
     * Extends a license by the variant’s license duration, from its expiry date or from now if it has already
     * expired. A license renewed with a variant whose licenses don’t expire no longer expires.
     */
    public function renewLicense(License $license, Variant $variant): bool
    {
        $now = new DateTime();
        $from = $license->expiryDate && $license->expiryDate > $now ? $license->expiryDate : $now;
        $license->expiryDate = $variant->getLicenseExpiryDate($from);

        return Elements::saveElement($license);
    }

    /**
     * Checks a line item that renews a license: the license must exist, be for the line item’s variant, belong
     * to the order’s customer once there is one, and be renewed once.
     */
    public function validateRenewal(LineItem $lineItem, Variant $variant, string $licenseKey): void
    {
        $customer = $lineItem->getOrder()?->getCustomer();
        /** @var License|null $license */
        $license = License::find()->licenseKey($licenseKey)->status(null)->one();

        $found = $customer
            ? $this->getRenewableLicense($licenseKey, $variant, $customer) !== null
            : $license?->variantId === $variant->id;

        if (!$found) {
            $lineItem->errors()->add('options', t('The license to renew wasn’t found.', category: 'commerce'));
        }

        if ($lineItem->qty > 1) {
            $lineItem->errors()->add('qty', t('A license renewal must have a quantity of 1.', category: 'commerce'));
        }
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        if ($this->isEnabled() && app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid) {
            $this->licenseOrder($event->order);
        }
    }

    public function handleOrderCompleted(OrderCompleted $event): void
    {
        if ($this->isEnabled() && !app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid) {
            $this->licenseOrder($event->order);
        }
    }

    /**
     * Blocks guests from paying for orders that contain licensed variants, when logged-in users are required.
     */
    public function handlePaymentProcessing(PaymentProcessing $event): void
    {
        if (
            !$this->isEnabled()
            || !app(Plugin::class)->getSettings()->requireLoggedInUserForLicenses
            || currentUser() !== null
        ) {
            return;
        }

        foreach ($event->order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();

            if ($purchasable instanceof Variant && $purchasable->issuesLicenses()) {
                $event->isValid = false;

                return;
            }
        }
    }

    /**
     * Assigns unassigned licenses to a newly activated user whose email matches.
     */
    public function handleUserActivated(UserActivated $event): void
    {
        if (!$this->isEnabled() || !app(Plugin::class)->getSettings()->autoAssignLicensesOnUserRegistration) {
            return;
        }

        /** @var License[] $licenses */
        $licenses = License::find()
            ->ownerEmail($event->user->email)
            ->ownerId(':empty:')
            ->status(null)
            ->all();

        foreach ($licenses as $license) {
            $license->userId = $event->user->id;
            Elements::saveElement($license);
        }
    }
}
