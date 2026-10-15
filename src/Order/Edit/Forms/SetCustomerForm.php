<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\Operations\SetCustomer;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Sets, creates from an email address, or removes the order's customer.
 *
 * @since 6.0.0
 */
final readonly class SetCustomerForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetCustomer::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Customer', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Customer', category: 'commerce'), ElementSelect::make('customerId')
                ->elementType(User::class)
                ->criteria(['status' => null])
                ->single()),
            Field::make(t('Create from Email', category: 'commerce'), Text::make('email')->inputType('email'))
                ->instructions(t('Used instead of the customer above, creating one if the email isn’t known.', category: 'commerce')),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return [
            'customerId' => $order->getCustomerId(),
            'email' => '',
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        $email = trim((string)($input['email'] ?? ''));

        return ['customerId' => $email !== '' ? $this->customerIdForEmail($email) : $this->nullableId($input['customerId'] ?? null)];
    }

    private function customerIdForEmail(string $email): int
    {
        Validator::make(['email' => $email], ['email' => ['email']])->validate();

        try {
            return Users::ensureUserByEmail($email)->id;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }
    }
}
