<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\Data\OrderEditSession;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Exceptions\OrderBeingEditedException;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Edit\Models\OrderEditSession as OrderEditSessionModel;
use CraftCms\Commerce\Order\Elements\Order;
use DateTimeImmutable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

use function CraftCms\Cms\t;

/**
 * Manages edit sessions: the changes an admin is making to a completed order, held until they're
 * committed or discarded.
 *
 * Each order has at most one session. Methods that take an order expect it to have been freshly
 * loaded, and leave it showing the result of the session's edit.
 *
 * @since 6.0.0
 */
#[Singleton]
class OrderEditSessions
{
    public const int STALE_AFTER_DAYS = 30;

    public function __construct(
        private readonly OrderOperations $operations,
    ) {
    }

    public function getSessionByOrderId(int $orderId): ?OrderEditSession
    {
        $model = OrderEditSessionModel::query()->where('orderId', $orderId)->first();

        return $model ? $this->toData($model) : null;
    }

    public function orderHasChanges(int $orderId): bool
    {
        return (bool)$this->getSessionByOrderId($orderId)?->hasChanges();
    }

    /**
     * Starts editing a completed order, or returns the user's existing session for it.
     *
     * @throws OrderBeingEditedException if another user is already editing the order.
     */
    public function start(Order $order, User $user): OrderEditSession
    {
        if (!$order->isCompleted) {
            throw new InvalidArgumentException('Only completed orders are edited in an edit session.');
        }

        $existing = $this->getSessionByOrderId($order->id);

        if ($existing) {
            if ($existing->belongsTo($user->id)) {
                return $existing;
            }

            throw new OrderBeingEditedException($existing, t('This order is being edited by someone else.', category: 'commerce'));
        }

        return $this->create($order, $user);
    }

    /**
     * Discards another user's session for the order, and starts one for this user.
     */
    public function takeOver(Order $order, User $user): OrderEditSession
    {
        return DB::transaction(function() use ($order, $user): OrderEditSession {
            $existing = $this->getSessionByOrderId($order->id);

            if ($existing?->belongsTo($user->id)) {
                return $existing;
            }

            if ($existing) {
                $this->discard($existing);

                Log::info(sprintf(
                    'User %s took over the edit session for order %s from user %s, discarding %s change(s).',
                    $user->id,
                    $order->id,
                    $existing->userId ?? 'unknown',
                    count($existing->edit->operations),
                ));
            }

            return $this->start($order, $user);
        });
    }

    /**
     * Applies the session's edit to the order, plus an operation the user is considering adding.
     *
     * @throws OperationNotApplicableException
     */
    public function preview(OrderEditSession $session, Order $order, ?OrderOperation $candidate = null): void
    {
        $edit = $candidate ? $session->edit->with($candidate) : $session->edit;

        $this->operations->preview($order, $edit);
    }

    /**
     * Adds an operation to the session, if the session's edit still applies with it.
     *
     * @throws OperationNotApplicableException
     */
    public function addOperation(OrderEditSession $session, Order $order, OrderOperation $operation): OrderEditSession
    {
        $edit = $session->edit->with($operation, $order);

        $this->operations->preview($order, $edit);

        return $this->update($session, $edit);
    }

    /**
     * @throws OperationNotApplicableException
     */
    public function removeOperation(OrderEditSession $session, Order $order, int $index): OrderEditSession
    {
        if (!isset($session->edit->operations[$index])) {
            throw new InvalidArgumentException("The edit session has no operation at index $index.");
        }

        $edit = $session->edit->without($index);

        $this->operations->preview($order, $edit);

        return $this->update($session, $edit);
    }

    /**
     * Saves the session's edit to the order, and ends the session.
     *
     * If the order has changed since the session started, the edit is previewed against the order as
     * it now is. If it still applies, the session is moved on to the order's current state so the
     * user can review it and commit again, and the order is left showing that preview.
     *
     * @return bool whether the order was saved. If not, the order has errors.
     * @throws StaleOrderException if the order has changed since the session started.
     * @throws OperationNotApplicableException if the order has changed in a way the edit no longer applies to.
     */
    public function commit(OrderEditSession $session, Order $order): bool
    {
        try {
            $saved = $this->operations->commit($order, $session->edit);
        } catch (StaleOrderException $exception) {
            $this->rebase($session, $order);

            throw $exception;
        }

        if ($saved) {
            $this->discard($session);
        }

        return $saved;
    }

    public function discard(OrderEditSession $session): void
    {
        OrderEditSessionModel::query()->where('id', $session->id)->delete();
    }

    /**
     * Deletes sessions that haven't been touched for {@see STALE_AFTER_DAYS} days.
     */
    public function purgeStaleSessions(): int
    {
        return OrderEditSessionModel::query()
            ->where('dateUpdated', '<', now()->subDays(self::STALE_AFTER_DAYS))
            ->delete();
    }

    /**
     * @throws OperationNotApplicableException
     */
    private function rebase(OrderEditSession $session, Order $order): void
    {
        $edit = new OrderEdit($session->edit->operations, DateTimeImmutable::createFromInterface($order->dateUpdated));

        $this->operations->preview($order, $edit);

        $this->update($session, $edit);
    }

    private function create(Order $order, User $user): OrderEditSession
    {
        try {
            $model = OrderEditSessionModel::query()->create([
                'orderId' => $order->id,
                'userId' => $user->id,
                'payload' => new OrderEdit([], DateTimeImmutable::createFromInterface($order->dateUpdated))->jsonSerialize(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->getSessionByOrderId($order->id) ?? throw new RuntimeException('The order’s edit session couldn’t be found.');

            throw new OrderBeingEditedException($existing, t('This order is being edited by someone else.', category: 'commerce'));
        }

        return $this->toData($model);
    }

    private function update(OrderEditSession $session, OrderEdit $edit): OrderEditSession
    {
        $model = OrderEditSessionModel::query()->findOrFail($session->id);
        $model->payload = $edit->jsonSerialize();
        $model->save();

        return $this->toData($model);
    }

    private function toData(OrderEditSessionModel $model): OrderEditSession
    {
        return new OrderEditSession(
            id: $model->id,
            orderId: $model->orderId,
            userId: $model->userId,
            edit: $this->operations->editFromArray($model->payload),
            dateCreated: DateTimeImmutable::createFromInterface($model->dateCreated),
            dateUpdated: DateTimeImmutable::createFromInterface($model->dateUpdated),
        );
    }
}
