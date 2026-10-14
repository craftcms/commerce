<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Cms\Support\Facades\Users;
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
            $order->setBillingAddress(null);
            $order->setShippingAddress(null);
            $order->autoSetAddresses();
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
}
