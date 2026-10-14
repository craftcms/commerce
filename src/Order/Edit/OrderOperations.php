<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Elements\Order;
use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
}
