<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class OrderStatus extends Component implements HasStoreInterface, Chippable
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public string $color = 'green';

    public ?string $description = null;

    public ?int $sortOrder = null;

    public bool $default = false;

    public ?DateTime $dateDeleted = null;

    public ?string $uid = null;

    public function __toString(): string
    {
        return $this->getUiLabel();
    }

    #[\Override]
    public function getUiLabel(): string
    {
        if ($this->dateDeleted !== null) {
            return t('{name} (Trashed)', ['name' => t($this->name ?? '', category: 'site')], category: 'commerce');
        }

        return t($this->name ?? '', category: 'site');
    }

    #[\Deprecated(message: 'in 5.6. Use [[getUiLabel()]] instead.')]
    public function getDisplayName(): string
    {
        return $this->getUiLabel();
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'handle' => [
                'required',
                'string',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique(Table::ORDERSTATUSES, 'handle')->where('storeId', $this->storeId)->ignore($this->id),
                function($attribute, $value, $fail) {
                    $reserved = ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create'];
                    if (in_array($value, $reserved, true)) {
                        $fail(t('"{value}" is a reserved word.', ['value' => $value], category: 'commerce'));
                    }
                },
            ],
        ];
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['emails', 'emailIds', 'labelHtml', 'uiLabel']);
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('orderstatuses/' . $this->id);
    }

    public function getEmailIds(): array
    {
        return array_column($this->getEmails(), 'id');
    }

    public function getEmails(): array
    {
        return $this->id ? app(Emails::class)->getAllEmailsByOrderStatusId($this->id) : [];
    }

    public function getLabelHtml(): string
    {
        return app(StatusHtml::class)->statusLabelHtml([
            'color' => htmlspecialchars($this->color, ENT_QUOTES | ENT_SUBSTITUTE),
            'label' => htmlspecialchars($this->getUiLabel(), ENT_QUOTES | ENT_SUBSTITUTE),
        ]) ?? '';
    }

    public function canDelete(): bool
    {
        // TODO: migrate to app(Orders::class) query once element migrated to src/
        $orderQuery = \craft\commerce\elements\Order::find()->trashed(null);
        return !$orderQuery->orderStatus($this)->one() && !$this->default;
    }

    public function getConfig(?array $emailIds = null): array
    {
        if ($emailIds === null) {
            $emailIds = $this->getEmailIds();
        }

        $emails = !empty($emailIds) ? DB::table(Table::EMAILS)->uidsByIds($emailIds) : [];
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'color' => $this->color,
            'description' => $this->description,
            'sortOrder' => $this->sortOrder ?? 99,
            'default' => $this->default,
            'emails' => !empty($emails) ? array_combine($emails, $emails) : [],
            'store' => $this->getStore()->uid,
        ];
    }

    #[\Override]
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return app(OrderStatuses::class)->getOrderStatusById($id);
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
