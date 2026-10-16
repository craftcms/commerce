<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Field\Exceptions\InvalidFieldException;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\Data\OrderEditSession;
use CraftCms\Commerce\Order\Edit\Data\OrderSummary;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Exceptions\OrderBeingEditedException;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Edit\Forms\OrderOperationForm;
use CraftCms\Commerce\Order\Edit\Forms\OrderOperationForms;
use CraftCms\Commerce\Order\Edit\Forms\SetAddressForm;
use CraftCms\Commerce\Order\Edit\Operations\SetAddress;
use CraftCms\Commerce\Order\Edit\Operations\SetFieldValues;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use DateTimeImmutable;
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
        private readonly OrderOperationForms $forms,
        private readonly UiResolver $uiResolver,
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

        return $this->applyToCart($order, $edit);
    }

    /**
     * The snapshots a line item and its adjustments were saved with.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $request->validate([
            'orderId' => ['required', 'integer'],
            'lineItemUid' => ['required', 'string'],
        ]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->canView($this->user()), 403, 'User not authorized to view this order.');

        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->uid !== $request->input('lineItemUid')) {
                continue;
            }

            $sections = [['label' => t('Line Item', category: 'commerce'), 'json' => $this->prettyJson($lineItem->getSnapshot())]];

            foreach ($lineItem->getAdjustments() as $adjustment) {
                $sections[] = ['label' => $adjustment->name, 'json' => $this->prettyJson($adjustment->getSourceSnapshot())];
            }

            return new JsonResponse(['title' => $lineItem->getDescription(), 'sections' => $sections]);
        }

        abort(404, t('The line item no longer exists.', category: 'commerce'));
    }

    /**
     * The UI of the form an action opens, for `openUiModal()`.
     */
    public function showForm(Request $request): JsonResponse
    {
        $order = $this->editableOrder($request);
        $this->previewOwnSession($order);
        $form = $this->operationForm($request);
        $params = $request->query();

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($form->ui($order, $params), new UiContext(values: $form->values($order, $params))),
            'title' => $form->title($order, $params),
            'submitLabel' => $form->submitLabel($order),
        ]);
    }

    /**
     * The order's custom fields as a slideout screen, for the slideout to post to `submitForm()`.
     */
    public function fieldsScreen(Request $request): CpScreenResponse
    {
        $order = $this->editableOrder($request);
        $this->previewOwnSession($order);

        return new CpScreenResponse()
            ->title(t('Edit Fields', category: 'commerce'))
            ->ui($this->fieldsUi($order), props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'submitForm'], [
                        'orderId' => $order->id,
                        'form' => SetFieldValues::type(),
                        'dateUpdated' => $request->input('dateUpdated'),
                    ]),
                ],
                'refreshUrl' => action([self::class, 'refreshFields'], ['orderId' => $order->id]),
            ]);
    }

    /**
     * The fields screen as the posted values would leave it, so conditional fields appear and
     * disappear as the user edits.
     */
    public function refreshFields(Request $request): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array'],
            'scope.*' => ['string'],
        ]);

        $order = $this->editableOrder($request);
        $this->previewOwnSession($order);

        foreach ($data['values']['fields'] ?? [] as $handle => $value) {
            try {
                $order->setFieldValueFromRequest((string)$handle, $value);
            } catch (InvalidFieldException) {
            }
        }

        return new JsonResponse(['ui' => $this->fieldsUi($order)->forScope($data['scope'])]);
    }

    /**
     * An order address' own fields as a slideout screen, for the slideout to post to `submitForm()`.
     */
    public function addressScreen(Request $request): CpScreenResponse
    {
        $order = $this->editableOrder($request);
        $this->previewOwnSession($order);
        $form = $this->addressForm();
        $params = ['addressType' => $request->input('addressType')];

        return new CpScreenResponse()
            ->title($form->title($order, $params))
            ->ui($form->screenUi($order, $params), props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'submitForm'], [
                        'orderId' => $order->id,
                        'form' => $form::type(),
                        'dateUpdated' => $request->input('dateUpdated'),
                        ...$params,
                    ]),
                ],
                'refreshUrl' => action([self::class, 'refreshAddress'], ['orderId' => $order->id, ...$params]),
            ]);
    }

    /**
     * The address screen as the posted values would leave it, so the fields that depend on the
     * country follow the user's choice.
     */
    public function refreshAddress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array'],
            'scope.*' => ['string'],
        ]);

        $order = $this->editableOrder($request);
        $this->previewOwnSession($order);

        return new JsonResponse([
            'ui' => $this->addressForm()
                ->screenUi($order, ['addressType' => $request->input('addressType')], $data['values'])
                ->forScope($data['scope']),
        ]);
    }

    private function addressForm(): SetAddressForm
    {
        $form = $this->forms->byType(SetAddress::type());
        assert($form instanceof SetAddressForm);

        return $form;
    }

    private function fieldsUi(Order $order): UiPayload
    {
        return app(FieldLayoutCompiler::class)->compile(
            $order->getFieldLayout(),
            $order,
            new UiContext(namespace: [], errors: [], mode: ControlMode::Editable, refreshable: true),
        );
    }

    /**
     * Turns a submitted form into its operation. A cart is saved straight away, and a completed
     * order's session takes the operation as another change.
     */
    public function submitForm(Request $request): JsonResponse
    {
        $order = $this->editableOrder($request);
        $form = $this->operationForm($request);

        if ($order->isCompleted) {
            return $this->addFormToSession($request, $order, $form);
        }

        $request->validate([
            'dateUpdated' => ['required', 'date'],
        ]);

        $operation = $this->operations->operationFromArray([
            ...$form->operationData($order, $request->all()),
            'type' => $form::type(),
        ]);

        return $this->applyToCart($order, new OrderEdit([$operation], new DateTimeImmutable($request->input('dateUpdated'))));
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

    private function addFormToSession(Request $request, Order $preview, OrderOperationForm $form): JsonResponse
    {
        $session = $this->previewOwnSession($preview);

        $operation = $this->operations->operationFromArray([
            ...$form->operationData($preview, $request->all()),
            'type' => $form::type(),
        ]);

        $order = $this->editableOrder($request);
        $original = OrderSummary::fromOrder($order);

        try {
            $session = $this->sessions->addOperation($session, $order, $operation);
        } catch (OperationNotApplicableException $exception) {
            return $this->notApplicableResponse($exception);
        }

        return $this->asJsonSuccess(t('Change added.', category: 'commerce'), $this->sessionData($session, $order, $original));
    }

    /**
     * Shows a completed order as its edit session would leave it, for the forms to start from.
     */
    private function previewOwnSession(Order $order): ?OrderEditSession
    {
        if (!$order->isCompleted) {
            return null;
        }

        $session = $this->ownSession($order);

        try {
            $this->sessions->preview($session, $order);
        } catch (OperationNotApplicableException $exception) {
            abort($this->notApplicableResponse($exception));
        }

        return $session;
    }

    private function applyToCart(Order $order, OrderEdit $edit): JsonResponse
    {
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

        return $this->asJsonSuccess(t('Cart updated.', category: 'commerce'), [
            'order' => OrderSummary::fromOrder($order),
        ]);
    }

    /** @param array<array-key, mixed> $data */
    private function prettyJson(array $data): string
    {
        return (string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function operationForm(Request $request): OrderOperationForm
    {
        $form = $this->forms->byType((string)$request->input('form'));
        abort_if(!$form, 404, 'Unknown order form.');

        return $form;
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
