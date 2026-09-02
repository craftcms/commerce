<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Typecast;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Models\Customer as CustomerRecord;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\PaymentSources;
use CraftCms\Commerce\Plugin\Plugin;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Support\ObjectState;

/**
 * @mixin Plugin
 */
trait HasCommerceMacros
{
    /**
     * Registers `craft.commerce`/`craft.orders`/`craft.products`/`craft.variants` Twig variable
     * macros, replacing the legacy `craft\commerce\web\twig\CraftVariableBehavior` attached to
     * `craft\web\twig\variables\CraftVariable` in `src-yii2/Plugin.php` (now deleted — that
     * behavior never reached the live `craft` Twig global under Craft 6 anyway).
     */
    private function registerVariableMacros(): void
    {
        $plugin = $this;
        CraftVariable::macro('commerce', fn() => $plugin);

        CraftVariable::macro('orders', function(array $criteria = []) {
            $query = Order::find();
            Typecast::configure($query, $criteria);

            return $query;
        });

        CraftVariable::macro('products', function(array $criteria = []) {
            $query = Product::find();
            Typecast::configure($query, $criteria);

            return $query;
        });

        CraftVariable::macro('variants', function(array $criteria = []) {
            $query = Variant::find();
            Typecast::configure($query, $criteria);

            return $query;
        });
    }

    /**
     * Reachable in Twig as `craft.commerce.getDonation()` (`craft.commerce` resolves to this
     * plugin instance via the `commerce` macro above), matching the legacy
     * `craft\commerce\plugin\Variables::getDonation()` trait method that used to live directly
     * on the legacy Plugin class for the same reason.
     */
    public function getDonation(): ?Donation
    {
        return Donation::find()->status(null)->one();
    }

    /**
     * Replaces the legacy Yii2 `StoreBehavior`/`CustomerBehavior`/`CustomerAddressBehavior` classes,
     * which no longer attach to anything — `Site`/`User`/`Address` extend the new
     * `CraftCms\Cms\Component\Component`, not `yii\base\Component`, so `attachBehavior()` doesn't exist
     * on them at all. `Macroable` (already `use`d by `Component`) is the replacement mechanism; its
     * `MacroableMagicMethods` concern makes registered macros transparently reachable via method-call
     * syntax (`$site->getStore()`), PHP magic-property syntax (`$site->store`), and Twig dot-notation
     * (`{{ site.store }}`) alike — verified empirically via `php artisan tinker` this session.
     */
    private function registerBehaviorMacros(): void
    {
        Site::macro('getStore', function(): ?Store {
            /** @var Site $this */
            return app(Stores::class)->getStoreBySiteId($this->id);
        });

        $this->registerCustomerMacros();
        $this->registerCustomerAddressMacros();
    }

    /**
     * Replaces `craft\commerce\behaviors\CustomerBehavior`, attached to `User` in Commerce 5.
     */
    private function registerCustomerMacros(): void
    {
        User::macro('getPrimaryBillingAddressId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryBillingAddressId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();
                ObjectState::set($this, 'primaryBillingAddressId', $customer?->primaryBillingAddressId);
            }

            return ObjectState::get($this, 'primaryBillingAddressId');
        });

        User::macro('setPrimaryBillingAddressId', function(?int $primaryBillingAddressId): void {
            ObjectState::set($this, 'primaryBillingAddressId', $primaryBillingAddressId);
        });

        User::macro('getPrimaryBillingAddress', function(): ?Address {
            /** @var User $this */
            /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is another macro registered above, not visible to static analysis) */
            return $this->getAddresses()->firstWhere('id', $this->getPrimaryBillingAddressId());
        });

        User::macro('getPrimaryShippingAddressId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryShippingAddressId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();
                ObjectState::set($this, 'primaryShippingAddressId', $customer?->primaryShippingAddressId);
            }

            return ObjectState::get($this, 'primaryShippingAddressId');
        });

        User::macro('setPrimaryShippingAddressId', function(?int $primaryShippingAddressId): void {
            ObjectState::set($this, 'primaryShippingAddressId', $primaryShippingAddressId);
        });

        User::macro('getPrimaryShippingAddress', function(): ?Address {
            /** @var User $this */
            /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is another macro registered above, not visible to static analysis) */
            return $this->getAddresses()->firstWhere('id', $this->getPrimaryShippingAddressId());
        });

        User::macro('setPrimaryPaymentSourceId', function(?int $paymentSourceId): void {
            ObjectState::set($this, 'primaryPaymentSourceId', $paymentSourceId);
        });

        User::macro('getPrimaryPaymentSourceId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryPaymentSourceId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();

                if (!$customer) {
                    return null;
                }

                if ($customer->primaryPaymentSourceId) {
                    ObjectState::set($this, 'primaryPaymentSourceId', $customer->primaryPaymentSourceId);
                } else {
                    /** @phpstan-ignore-next-line method.notFound (getPrimaryPaymentSource() is another macro registered below, not visible to static analysis) */
                    $paymentSource = $this->getPrimaryPaymentSource();
                    ObjectState::set($this, 'primaryPaymentSourceId', $paymentSource?->id);
                }
            }

            return ObjectState::get($this, 'primaryPaymentSourceId');
        });

        User::macro('getPrimaryPaymentSource', function(): ?PaymentSource {
            /** @var User $this */
            $paymentSources = app(PaymentSources::class)->getAllPaymentSourcesByCustomerId(customerId: $this->id);

            if ($paymentSources->isEmpty()) {
                return null;
            }

            $primaryId = ObjectState::get($this, 'primaryPaymentSourceId');

            if (!$primaryId) {
                return $paymentSources->first();
            }

            return $paymentSources->firstWhere('id', $primaryId);
        });

        User::macro('getActiveCarts', function(): array {
            /** @var User $this */
            $edge = app(Carts::class)->getActiveCartEdgeDuration();

            return Order::find()
                ->customer($this)
                ->isCompleted(false)
                ->where('elements.dateUpdated', '>=', $edge)
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('elements.dateUpdated', 'desc')
                ->all();
        });

        User::macro('getInactiveCarts', function(): array {
            /** @var User $this */
            $edge = app(Carts::class)->getActiveCartEdgeDuration();

            return Order::find()
                ->customer($this)
                ->isCompleted(false)
                ->where('elements.dateUpdated', '<', $edge)
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('elements.dateUpdated', 'asc')
                ->all();
        });

        User::macro('getOrders', function(): array {
            /** @var User $this */
            return Order::find()
                ->customer($this)
                ->isCompleted()
                ->withAll()
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('dateOrdered', 'desc')
                ->all();
        });
    }

    /**
     * Replaces `craft\commerce\behaviors\CustomerAddressBehavior`, attached to `Address` in Commerce 5.
     */
    private function registerCustomerAddressMacros(): void
    {
        Address::macro('getIsPrimaryBilling', function(): bool {
            /** @var Address $this */
            if (!ObjectState::has($this, 'isPrimaryBilling')) {
                $owner = $this->getPrimaryOwner();
                /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is a macro registered in registerCustomerMacros(), not visible to static analysis) */
                $value = $this->id && $owner instanceof User && $this->id === $owner->getPrimaryBillingAddressId();
                ObjectState::set($this, 'isPrimaryBilling', $value);
            }

            return ObjectState::get($this, 'isPrimaryBilling');
        });

        Address::macro('setIsPrimaryBilling', function(bool|string $value): void {
            ObjectState::set($this, 'isPrimaryBilling', (bool) $value);
        });

        Address::macro('hasIsPrimaryBillingBeenSet', function(): bool {
            /** @var Address $this */
            return ObjectState::has($this, 'isPrimaryBilling');
        });

        Address::macro('getIsPrimaryShipping', function(): bool {
            /** @var Address $this */
            if (!ObjectState::has($this, 'isPrimaryShipping')) {
                $owner = $this->getPrimaryOwner();
                /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is a macro registered in registerCustomerMacros(), not visible to static analysis) */
                $value = $this->id && $owner instanceof User && $this->id === $owner->getPrimaryShippingAddressId();
                ObjectState::set($this, 'isPrimaryShipping', $value);
            }

            return ObjectState::get($this, 'isPrimaryShipping');
        });

        Address::macro('setIsPrimaryShipping', function(bool|string $value): void {
            ObjectState::set($this, 'isPrimaryShipping', (bool) $value);
        });

        Address::macro('hasIsPrimaryShippingBeenSet', function(): bool {
            /** @var Address $this */
            return ObjectState::has($this, 'isPrimaryShipping');
        });
    }
}
