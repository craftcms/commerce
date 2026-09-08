<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\DeletionBlockers;

use CraftCms\Cms\Cp\Html\ElementIndexHtml;
use CraftCms\Cms\Element\DeletionBlockers\BaseDeletionBlocker;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Collection;
use Override;

use function CraftCms\Cms\t;

class OrderCustomersDeletionBlocker extends BaseDeletionBlocker
{
    /**
     * @var Collection<int, int>
     */
    public Collection $orderIds;

    /**
     * @param ElementCollection<int, covariant \CraftCms\Cms\Element\Contracts\ElementInterface> $elements
     * @param array<string, mixed> $config
     */
    public function __construct(ElementCollection $elements, bool $hardDelete, array $config = [])
    {
        parent::__construct($elements, $hardDelete, $config);

        $this->orderIds = Order::find()
            ->customerId($this->elements->ids()->all())
            ->isCompleted()
            ->status(null)
            ->limit(null)
            ->collectIds();
    }

    #[Override]
    public function isActive(): bool
    {
        return $this->orderIds->isNotEmpty();
    }

    #[Override]
    public function getSummary(): string
    {
        return t('{numOrders, number} {numOrders, plural, =1{order is} other{orders are}} associated with the {numUsers, plural, =1{user} other{users}}.', [
            'numOrders' => $this->orderIds->count(),
            'numUsers' => $this->elements->count(),
        ], category: 'commerce');
    }

    #[Override]
    public function getActions(): array
    {
        $numOrders = $this->orderIds->count();

        return [
            [
                'icon' => 'user-plus',
                'label' => t('Reassign {numOrders, plural, =1{order} other{orders}}', [
                    'numOrders' => $numOrders,
                ], category: 'commerce'),
                'callback' => Html::jsWithVars(fn($userIds) => <<<JS
                    new Craft.CpModal('commerce/orders/reassign-modal', {
                      params: {
                        oldUserIds: $userIds,
                      },
                      onSubmit: (ev) => {
                        resolve(ev.response.data.message);
                      },
                      onCancel: () => {
                        reject();
                      },
                    });
                    JS, [
                    $this->elements->ids()->all(),
                ]),
            ],
            [
                'icon' => 'user-minus',
                'label' => t('Remove customer data', category: 'commerce'),
                'callback' => Html::jsWithVars(fn($orderIds) => <<<JS
                    new Craft.CpModal('commerce/orders/remove-customer-data-modal', {
                      params: {
                        orderIds: $orderIds,
                      },
                      onSubmit: (ev) => {
                        resolve(ev.response.data.message);
                      },
                      onCancel: () => {
                        reject();
                      },
                    });
                    JS, [
                    $this->orderIds->all(),
                ]),
            ],
            [
                'icon' => 'trash',
                'label' => t('Delete {type}', [
                    'type' => $numOrders === 1 ? Order::lowerDisplayName() : Order::pluralLowerDisplayName(),
                ], category: 'app'),
                'destructive' => true,
                'callback' => Html::jsWithVars(fn($elementType, $entryIds, $message) => <<<JS
                    new Craft.ElementDeletionManager($elementType, $entryIds, {
                      onSuccess: () => {
                        resolve($message);
                      },
                      onCancel: () => {
                        reject();
                      },
                    });
                    JS, [
                        Order::class,
                        $this->orderIds->all(),
                        t('{type} deleted.', [
                            'type' => $numOrders === 1 ? Order::displayName() : Order::pluralDisplayName(),
                        ], category: 'app'),
                ]),
            ],
        ];
    }

    #[Override]
    public function getDetails(): ?string
    {
        return app(ElementIndexHtml::class)->html(Order::class, [
            'context' => 'pane',
            'defaultTableColumns' => [
                ['customer'],
                ['orderStatus'],
                ['dateOrdered'],
            ],
            'defaultSort' => ['dateOrdered', 'desc'],
            'sources' => false,
            'jsSettings' => [
                'criteria' => [
                    'customerId' => $this->elements->ids()->all(),
                    'status' => null,
                ],
            ],
        ]);
    }
}
