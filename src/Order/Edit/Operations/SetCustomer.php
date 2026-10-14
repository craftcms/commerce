<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Sets or removes the order's customer.
 *
 * A cart given a new customer takes that customer's primary addresses, when its store auto-sets
 * new cart addresses.
 *
 * @since 6.0.0
 */
final readonly class SetCustomer extends OrderOperation
{
    public function __construct(
        public ?int $customerId,
    ) {
    }

    public static function type(): string
    {
        return 'setCustomer';
    }

    public static function rules(): array
    {
        return [
            'customerId' => ['present', 'nullable', 'integer'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(isset($data['customerId']) ? (int)$data['customerId'] : null);
    }

    public function toArray(): array
    {
        return ['customerId' => $this->customerId];
    }

    public function apply(Order $order): void
    {
        $customer = $this->customerId !== null ? Users::getUserById($this->customerId) : null;

        if ($this->customerId !== null && !$customer) {
            throw new OperationNotApplicableException(t('The customer no longer exists.', category: 'commerce'));
        }

        $customerChanged = $order->getCustomerId() !== $this->customerId;
        $order->setCustomer($customer);

        if ($customer && $customerChanged && !$order->isCompleted) {
            $this->autoSetAddresses($order, $customer);
        }

        // The payment source or gateway may not be valid for the new customer.
        try {
            $order->getPaymentSource();
            $order->getGateway();
        } catch (Throwable) {
            $order->paymentSourceId = null;
            $order->gatewayId = null;
        }
    }

    /**
     * Gives a cart its new customer's primary addresses, as {@see Order::autoSetAddresses()} does, but
     * as in-memory copies that are only saved along with the order.
     */
    private function autoSetAddresses(Order $order, User $customer): void
    {
        OrderAddressType::Shipping->setAddress($order, null);
        OrderAddressType::Billing->setAddress($order, null);

        if (!$order->getStore()->getAutoSetNewCartAddresses()) {
            return;
        }

        $primaryAddresses = [
            /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddress() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
            OrderAddressType::Shipping->value => $customer->getPrimaryShippingAddress(),
            /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddress() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
            OrderAddressType::Billing->value => $customer->getPrimaryBillingAddress(),
        ];

        foreach ($primaryAddresses as $addressType => $address) {
            if ($address instanceof Address) {
                OrderAddressType::from($addressType)->setAddress($order, OrderAddressType::attributesOf($address), $address->id);
            }
        }
    }
}
