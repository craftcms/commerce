<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\deletionblockers;

use Craft;
use craft\commerce\elements\Order;
use craft\elements\deletionblockers\BaseDeletionBlocker;
use craft\helpers\Cp;
use craft\helpers\Html;
use Illuminate\Support\Collection;

/**
 * Order Customers Deletion Blocker.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class OrderCustomersDeletionBlocker extends BaseDeletionBlocker
{
    /**
     * @var Collection<int>
     */
    public Collection $orderIds;

    public function init()
    {
        $this->orderIds = Order::find()
            ->customerId($this->elements->ids()->all())
            ->isCompleted()
            ->status(null)
            ->limit(null)
            ->collectIds();

        parent::init();
    }

    public function isActive(): bool
    {
        return $this->orderIds->isNotEmpty();
    }

    public function getSummary(): string
    {
        return Craft::t('commerce', '{numOrders, number} {numOrders, plural, =1{order is} other{orders are}} associated with the {numUsers, plural, =1{user} other{users}}.', [
            'numOrders' => $this->orderIds->count(),
            'numUsers' => $this->elements->count(),
        ]);
    }

    public function getActions(): array
    {
        $numOrders = $this->orderIds->count();

        return [
            [
                'icon' => 'user-plus',
                'label' => Craft::t('commerce', 'Reassign {numOrders, plural, =1{order} other{orders}}', [
                    'numOrders' => $numOrders,
                ]),
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
                'label' => Craft::t('commerce', 'Remove customer data'),
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
                'label' => Craft::t('app', 'Delete {type}', [
                    'type' => $numOrders === 1 ? Order::lowerDisplayName() : Order::pluralLowerDisplayName(),
                ]),
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
                        Craft::t('app', '{type} deleted.', [
                            'type' => $numOrders === 1 ? Order::displayName() : Order::pluralDisplayName(),
                        ]),
                ]),
            ],
        ];
    }

    public function getDetails(): ?string
    {
        return Cp::elementIndexHtml(Order::class, [
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
