<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Elements\Order;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

use function CraftCms\Cms\t;

/**
 * Turns submitted or stored order edits into operations, and applies them to orders.
 *
 * @since 6.0.0
 */
class OrderOperations
{
    public function __construct(
        private readonly OrderOperationTypes $types,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function operationFromArray(array $data, string $errorPrefix = ''): OrderOperation
    {
        $class = $this->types->byType((string)($data['type'] ?? ''));

        if ($class === null) {
            throw ValidationException::withMessages([
                $errorPrefix . 'type' => t('Unknown order operation.', category: 'commerce'),
            ]);
        }

        $validator = Validator::make($data, $class::rules());

        if ($validator->fails()) {
            $messages = [];
            foreach ($validator->errors()->messages() as $key => $keyMessages) {
                $messages[$errorPrefix . $key] = $keyMessages;
            }

            throw ValidationException::withMessages($messages);
        }

        return $class::fromArray($data);
    }

    /**
     * @param array{version?: int, baseDateUpdated?: string|null, operations?: list<array<string, mixed>>} $data
     * @throws ValidationException
     */
    public function editFromArray(array $data): OrderEdit
    {
        if (($data['version'] ?? null) !== OrderEdit::VERSION) {
            throw ValidationException::withMessages([
                'version' => t('This order edit was made with a different version of Commerce.', category: 'commerce'),
            ]);
        }

        $operations = [];
        foreach ($data['operations'] ?? [] as $index => $operation) {
            $operations[] = $this->operationFromArray($operation, "operations.$index.");
        }

        return new OrderEdit(
            $operations,
            isset($data['baseDateUpdated']) ? new DateTimeImmutable($data['baseDateUpdated']) : null,
        );
    }

    /**
     * Applies operations to the order, in memory.
     *
     * Completed orders are left as the operations make them, so manual prices and adjustments
     * stand. Carts are then recalculated, the same as after any other cart change.
     *
     * @param list<OrderOperation> $operations
     * @throws OperationNotApplicableException with the index of the operation that couldn't be applied.
     */
    public function apply(Order $order, array $operations): void
    {
        $order->setRecalculationMode($order->isCompleted ? Order::RECALCULATION_MODE_NONE : Order::RECALCULATION_MODE_ALL);

        foreach ($operations as $index => $operation) {
            try {
                $operation->apply($order);
            } catch (OperationNotApplicableException $exception) {
                throw $exception->forOperation($index);
            }
        }

        if (!$order->isCompleted) {
            $order->recalculate();
        }
    }

    /**
     * What each operation in an edit changes, in order, for listing the edit.
     *
     * The operations are replayed on the given order, which should be as it was before the edit,
     * and each is summarised just before it applies. Nothing is saved, but the order is left as the
     * operations leave it.
     *
     * @param list<OrderOperation> $operations
     * @return list<string|null> A summary per operation, `null` where it has none.
     */
    public function summaries(Order $order, array $operations): array
    {
        $summaries = array_fill(0, count($operations), null);
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);

        DB::beginTransaction();

        try {
            foreach ($operations as $index => $operation) {
                try {
                    $summaries[$index] = $operation->summary($order);
                    $operation->apply($order);
                } catch (OperationNotApplicableException|InvalidArgumentException) {
                    break;
                }
            }
        } finally {
            DB::rollBack();
        }

        return $summaries;
    }

    /**
     * Applies an edit to the order to show what it would do, without saving anything.
     *
     * The operations themselves change the order in memory only. Anything else that writes to the
     * database along the way, such as a plugin's event listener, is rolled back.
     *
     * @throws OperationNotApplicableException
     */
    public function preview(Order $order, OrderEdit $edit): void
    {
        DB::beginTransaction();

        try {
            $this->apply($order, $edit->operations);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Applies an edit to the order and saves it, once.
     *
     * The edit is rejected if the order has changed since the edit was made against it (its
     * `baseDateUpdated`), or since the given order was loaded.
     *
     * @return bool whether the order was saved. If not, the order has errors.
     * @throws StaleOrderException
     * @throws OperationNotApplicableException
     */
    public function commit(Order $order, OrderEdit $edit): bool
    {
        return DB::transaction(function() use ($order, $edit): bool {
            $savedDateUpdated = DB::table(CmsTable::ELEMENTS)
                ->where('id', $order->id)
                ->lockForUpdate()
                ->value('dateUpdated');

            $saved = $savedDateUpdated ? new DateTimeImmutable($savedDateUpdated, new DateTimeZone('UTC')) : null;

            if (!self::sameMoment($saved, $order->dateUpdated) || ($edit->baseDateUpdated && !self::sameMoment($saved, $edit->baseDateUpdated))) {
                throw new StaleOrderException(t('This order has changed since it was loaded.', category: 'commerce'));
            }

            $this->apply($order, $edit->operations);

            $order->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

            return $order->validate(null, false) && Elements::saveElement($order, false);
        });
    }

    private static function sameMoment(?DateTimeInterface $a, ?DateTimeInterface $b): bool
    {
        return $a?->getTimestamp() === $b?->getTimestamp();
    }
}
