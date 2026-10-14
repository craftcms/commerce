<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\Data\OrderEditSession;
use CraftCms\Commerce\Order\Edit\Data\OrderSummary;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Exceptions\OrderBeingEditedException;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Edits orders through operations: straight away for carts, and through an edit session for
 * completed orders.
 *
 * @since 6.0.0
 */
class OrderEditController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly OrderOperations $operations,
        private readonly OrderEditSessions $sessions,
    ) {
    }

    /**
     * Applies operations to a cart and saves it.
     */
    public function apply(Request $request): JsonResponse
    {
        $order = $this->editableOrder($request);
        abort_if($order->isCompleted, 400, 'Completed orders are edited in an edit session.');

        $request->validate([
            'dateUpdated' => ['required', 'date'],
            'operations' => ['required', 'array', 'min:1'],
        ]);

        $edit = $this->operations->editFromArray([
            'version' => OrderEdit::VERSION,
            'baseDateUpdated' => $request->input('dateUpdated'),
            'operations' => array_values($request->input('operations')),
        ]);

        try {
            $saved = $this->operations->commit($order, $edit);
        } catch (StaleOrderException) {
            return $this->staleResponse();
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        if (!$saved) {
            return $this->orderErrorsResponse($order);
        }

        return $this->asJsonSuccess(data: [
            'order' => OrderSummary::fromOrder($order),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);

        try {
            $session = $this->sessions->start($order, $this->user());
        } catch (OrderBeingEditedException $exception) {
            return $this->beingEditedResponse($exception->session);
        }

        return $this->sessionResponse($session, $order);
    }

    public function takeOver(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);

        return $this->sessionResponse($this->sessions->takeOver($order, $this->user()), $order);
    }

    /**
     * Shows what the session's edit does to the order, optionally with an operation the user is
     * considering adding.
     */
    public function preview(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);
        $session = $this->ownSession($order);
        $original = OrderSummary::fromOrder($order);

        $candidate = $request->has('operation')
            ? $this->operations->operationFromArray((array)$request->input('operation'), 'operation.')
            : null;

        try {
            $this->sessions->preview($session, $order, $candidate);
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        return $this->sessionResponse($session, $order, $original);
    }

    public function addOperation(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);
        $session = $this->ownSession($order);
        $original = OrderSummary::fromOrder($order);
        $operation = $this->operations->operationFromArray((array)$request->input('operation'), 'operation.');

        try {
            $session = $this->sessions->addOperation($session, $order, $operation);
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        return $this->sessionResponse($session, $order, $original);
    }

    public function removeOperation(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);
        $session = $this->ownSession($order);
        $original = OrderSummary::fromOrder($order);
        $index = (int)$request->input('index');

        if (!isset($session->edit->operations[$index])) {
            throw ValidationException::withMessages([
                'index' => t('The edit has no change at that position.', category: 'commerce'),
            ]);
        }

        try {
            $session = $this->sessions->removeOperation($session, $order, $index);
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        return $this->sessionResponse($session, $order, $original);
    }

    /**
     * Saves the session's edit to the order.
     *
     * If the order changed since the session started, nothing is saved and the response is a 409
     * with the edit previewed against the order as it now is, for the user to review and commit
     * again.
     */
    public function commit(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);
        $session = $this->ownSession($order);
        $original = OrderSummary::fromOrder($order);

        try {
            $saved = $this->sessions->commit($session, $order);
        } catch (StaleOrderException) {
            $session = $this->sessions->getSessionByOrderId($order->id) ?? $session;

            return new JsonResponse([
                'message' => t('This order has changed since you started editing it. Review the changes and update the order again.', category: 'commerce'),
                ...$this->sessionData($session, $order, $original),
            ], 409);
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        if (!$saved) {
            return $this->orderErrorsResponse($order);
        }

        return $this->asJsonSuccess(t('Order updated.', category: 'commerce'), [
            'order' => OrderSummary::fromOrder($order),
        ]);
    }

    public function discard(Request $request): JsonResponse
    {
        $order = $this->editableCompletedOrder($request);
        $this->sessions->discard($this->ownSession($order));

        return $this->asJsonSuccess(data: [
            'order' => OrderSummary::fromOrder($order),
        ]);
    }

    private function editableOrder(Request $request): Order
    {
        $request->validate([
            'orderId' => ['required', 'integer'],
        ]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->canView($this->user()) && $order->canSave($this->user()), 403, 'User not authorized to edit this order.');

        return $order;
    }

    private function editableCompletedOrder(Request $request): Order
    {
        $order = $this->editableOrder($request);
        abort_unless($order->isCompleted, 400, 'Carts are edited without an edit session.');

        return $order;
    }

    private function ownSession(Order $order): OrderEditSession
    {
        $session = $this->sessions->getSessionByOrderId($order->id);
        abort_if(!$session, 404, t('This order isn’t being edited.', category: 'commerce'));

        if (!$session->belongsTo($this->user()->id)) {
            abort($this->beingEditedResponse($session));
        }

        return $session;
    }

    private function user(): User
    {
        $user = currentUserElement();
        abort_if(!$user, 403);

        return $user;
    }

    private function sessionResponse(OrderEditSession $session, Order $order, ?OrderSummary $original = null): JsonResponse
    {
        return $this->asJsonSuccess(data: $this->sessionData($session, $order, $original));
    }

    /**
     * @return array{session: OrderEditSession, original: OrderSummary|null, order: OrderSummary, stale: bool}
     */
    private function sessionData(OrderEditSession $session, Order $order, ?OrderSummary $original = null): array
    {
        return [
            'session' => $session,
            'original' => $original,
            'order' => OrderSummary::fromOrder($order),
            'stale' => $session->edit->baseDateUpdated?->getTimestamp() !== $order->dateUpdated?->getTimestamp(),
        ];
    }

    private function beingEditedResponse(OrderEditSession $session): JsonResponse
    {
        $editor = $session->userId ? User::find()->id($session->userId)->status(null)->one() : null;

        return new JsonResponse([
            'message' => t('This order is being edited by {name}.', [
                'name' => $editor?->getFriendlyName() ?? t('another user', category: 'commerce'),
            ], category: 'commerce'),
            'session' => [
                'userId' => $session->userId,
                'userName' => $editor?->getFriendlyName(),
                'dateCreated' => $session->dateCreated->format(DATE_ATOM),
                'dateUpdated' => $session->dateUpdated->format(DATE_ATOM),
            ],
        ], 423);
    }

    private function staleResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => t('This order has changed since it was loaded. Reload it and try again.', category: 'commerce'),
        ], 409);
    }

    private function notApplicableResponse(OperationNotApplicableException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => $exception->getMessage(),
            'operationIndex' => $exception->operationIndex,
        ], 422);
    }

    private function orderErrorsResponse(Order $order): JsonResponse
    {
        return new JsonResponse([
            'message' => t('Couldn’t save order.', category: 'commerce'),
            'errors' => $order->errors()->getMessages(),
        ], 422);
    }
}
