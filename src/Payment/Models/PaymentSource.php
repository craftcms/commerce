<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use Illuminate\Validation\Rule;

class PaymentSource extends Component
{
    public ?int $id = null;

    public int $customerId;

    public int $gatewayId;

    public string $token;

    public string $description;

    public string $response;

    private ?User $_customer = null;

    private ?GatewayInterface $_gateway = null;

    public function __toString(): string
    {
        return $this->token;
    }

    public function getCustomer(): ?User
    {
        if (!isset($this->_customer)) {
            $this->_customer = Users::getUserById($this->customerId);
        }

        return $this->_customer;
    }

    public function getIsPrimary(): bool
    {
        $customer = $this->getCustomer();
        return $customer && $customer->primaryPaymentSourceId === $this->id;
    }

    #[\Deprecated(message: 'in 4.0.0. Use [[getCustomer()]] instead.')]
    public function getUser(): ?User
    {
        Deprecator::log('PaymentSource::getUser()', 'The `PaymentSource::getUser()` is deprecated, use the `PaymentSource::getCustomer()` instead.');
        return $this->getCustomer();
    }

    public function getGateway(): ?GatewayInterface
    {
        if ($this->_gateway === null && $this->gatewayId) {
            $this->_gateway = app(Gateways::class)->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'token' => ['required', 'string', Rule::unique(Table::PAYMENTSOURCES)->where(fn($q) => $q->where('gatewayId', $this->gatewayId))],
            'gatewayId' => ['required', 'integer'],
            'customerId' => ['required', 'integer'],
            'description' => ['required', 'string'],
        ];
    }
}
