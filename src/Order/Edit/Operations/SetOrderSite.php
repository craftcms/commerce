<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Sets the site the order belongs to, and the order's language with it.
 *
 * @since 6.0.0
 */
final readonly class SetOrderSite extends OrderOperation
{
    public function __construct(
        public int $orderSiteId,
    ) {
    }

    public static function type(): string
    {
        return 'setOrderSite';
    }

    public static function rules(): array
    {
        return [
            'orderSiteId' => ['required', 'integer'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self((int)$data['orderSiteId']);
    }

    public function toArray(): array
    {
        return ['orderSiteId' => $this->orderSiteId];
    }

    public function apply(Order $order): void
    {
        $site = Sites::getSiteById($this->orderSiteId);

        if (!$site) {
            throw new OperationNotApplicableException(t('The site no longer exists.', category: 'commerce'));
        }

        $order->orderSiteId = $site->id;
        $order->orderLanguage = $site->language;
    }
}
