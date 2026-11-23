<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\OrderStatusException;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The parts of an order's life that save straight away: changing a completed order's status and
 * completing a cart.
 *
 * Neither goes through an edit session, so both refuse while a session has changes waiting. See
 * {@see OrderEditGuard}.
 *
 * @since 6.0.0
 */
class OrderLifecycleController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly OrderEditGuard $guard,
        private readonly OrderStatuses $statuses,
        private readonly UiResolver $uiResolver,
    ) {
    }

    /**
     * The UI of the status change form, for `openUiModal()`.
     */
    public function statusForm(Request $request): JsonResponse
    {
        $order = $this->completedOrder($request);

        $options = $this->statuses->getAllOrderStatuses($order->storeId)
            ->map(fn($status) => [
                'label' => $status->getUiLabel(),
                'value' => (string)$status->id,
                'data' => ['indicator' => ['fill' => $status->color]],
            ])
            ->values()
            ->all();

        $ui = Ui::make([
            Field::make(t('Status', category: 'commerce'), Combobox::make('orderStatusId')->options($options)->requireOptionMatch()->showAllOnEmpty())->required(),
            Field::make(t('Message', category: 'commerce'), Textarea::make('message')),
            Field::make(t('Suppress emails', category: 'commerce'), Lightswitch::make('suppressEmails')),
        ]);

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($ui, new UiContext(values: [
                'orderStatusId' => (string)$order->orderStatusId,
                'message' => '',
                'suppressEmails' => false,
            ])),
            'title' => t('Change Status', category: 'commerce'),
            'submitLabel' => t('Change Status', category: 'commerce'),
        ]);
    }

    /**
     * Changes a completed order's status, optionally with a message for its history, and without
     * sending the status' emails when asked.
     */
    public function changeStatus(Request $request): JsonResponse
    {
        $order = $this->completedOrder($request);

        $data = $request->validate([
            'orderStatusId' => ['required', 'integer'],
            'message' => ['nullable', 'string'],
            'suppressEmails' => ['boolean'],
        ]);

        if (!$this->statuses->getOrderStatusById((int)$data['orderStatusId'], $order->storeId)) {
            return new JsonResponse([
                'message' => t('The status doesn’t exist.', category: 'commerce'),
                'errors' => ['orderStatusId' => [t('The status doesn’t exist.', category: 'commerce')]],
            ], 422);
        }

        $order->orderStatusId = (int)$data['orderStatusId'];
        $order->message = ($data['message'] ?? '') === '' ? null : $data['message'];
        $order->suppressEmails = (bool)($data['suppressEmails'] ?? false);
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $order->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

        if (!$order->validate(null, false) || !Elements::saveElement($order, false)) {
            return new JsonResponse([
                'message' => t('Couldn’t save order.', category: 'commerce'),
                'errors' => $order->errors()->getMessages(),
            ], 422);
        }

        return $this->asJsonSuccess(t('Status changed.', category: 'commerce'));
    }

    /**
     * Completes a cart, giving it the store's default order status.
     */
    public function complete(Request $request): JsonResponse
    {
        $order = $this->editableOrder($request);
        abort_if($order->isCompleted, 400, t('The order is already completed.', category: 'commerce'));

        try {
            $completed = $order->markAsComplete();
        } catch (OrderStatusException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return new JsonResponse(['message' => t('Couldn’t complete the cart.', category: 'commerce')], 422);
        }

        if (!$completed) {
            return new JsonResponse([
                'message' => t('Couldn’t complete the cart.', category: 'commerce'),
                'errors' => $order->errors()->getMessages(),
            ], 422);
        }

        return $this->asJsonSuccess(t('Cart completed.', category: 'commerce'));
    }

    private function completedOrder(Request $request): Order
    {
        $order = $this->editableOrder($request);
        abort_unless($order->isCompleted, 400, t('Carts don’t have a status.', category: 'commerce'));

        return $order;
    }

    private function editableOrder(Request $request): Order
    {
        $request->validate(['orderId' => ['required', 'integer']]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));

        $user = currentUserElement();
        abort_unless($user && $order->canView($user) && $order->canSave($user), 403, 'User not authorized to edit this order.');

        $reason = $this->guard->blockReason($order, $user->id);
        abort_if($reason !== null, 423, (string)$reason);

        return $order;
    }
}
