<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Store\Data\Store;

// Commerce registers these macros when the plugin boots (see HasCommerceMacros), which only
// happens when it's installed. Registering them here lets PHPStan resolve them in any environment.
Site::macro('getStore', fn(): ?Store => null);

User::macro('getPrimaryBillingAddressId', fn(): ?int => null);
User::macro('setPrimaryBillingAddressId', function(?int $primaryBillingAddressId): void {
});
User::macro('getPrimaryBillingAddress', fn(): ?Address => null);
User::macro('getPrimaryShippingAddressId', fn(): ?int => null);
User::macro('setPrimaryShippingAddressId', function(?int $primaryShippingAddressId): void {
});
User::macro('getPrimaryShippingAddress', fn(): ?Address => null);
User::macro('getPrimaryPaymentSourceId', fn(): ?int => null);
User::macro('setPrimaryPaymentSourceId', function(?int $paymentSourceId): void {
});
User::macro('getPrimaryPaymentSource', fn(): ?PaymentSource => null);
User::macro('getActiveCarts', fn(): array => []);
User::macro('getInactiveCarts', fn(): array => []);
User::macro('getOrders', fn(): array => []);

Address::macro('getIsPrimaryBilling', fn(): bool => false);
Address::macro('setIsPrimaryBilling', function(bool|string $value): void {
});
Address::macro('hasIsPrimaryBillingBeenSet', fn(): bool => false);
Address::macro('getIsPrimaryShipping', fn(): bool => false);
Address::macro('setIsPrimaryShipping', function(bool|string $value): void {
});
Address::macro('hasIsPrimaryShippingBeenSet', fn(): bool => false);
