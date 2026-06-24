<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use function CraftCms\Cms\t;

class LineItemStatus extends Component implements HasStoreInterface, Chippable
{
    public ?int $id = null;

    public ?int $storeId = null;

    public ?string $name = null;

    public ?string $handle = null;

    public string $color = 'green';

    public ?int $sortOrder = null;

    public bool $default = false;

    public bool $isArchived = false;

    public ?DateTime $dateArchived = null;

    public ?string $uid = null;

    public function __toString(): string
    {
        return $this->getUiLabel();
    }

    #[\Override]
    public function getUiLabel(): string
    {
        return t($this->name ?? '', category: 'site');
    }

    #[\Override]
    public static function get(int|string $id): ?static
    {
        $site = app(RequestedSite::class)->get();
        $storeId = $site ? Plugin::getInstance()->getStores()->getStoreBySiteId($site->id)?->id : null;

        // TODO: migrate to app(LineItemStatuses::class)->getLineItemStatusById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id, $storeId);
    }

    #[\Override]
    public function getId(): string|int|null
    {
        return $this->id;
    }

    #[\Override]
    public function getStore(): \craft\commerce\models\Store
    {
        if (!$store = Plugin::getInstance()->getStores()->getStoreById($this->storeId)) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/settings/lineitemstatuses/' . $this->getStore()->handle . '/' . $this->id);
    }

    public function getLabelHtml(): string
    {
        return app(StatusHtml::class)->statusLabelHtml([
            'label' => e($this->getUiLabel()),
            'color' => e($this->color),
        ]) ?? '';
    }

    public function getConfig(): array
    {
        return [
            'store' => $this->getStore()->uid,
            'name' => $this->name,
            'handle' => $this->handle,
            'color' => $this->color,
            'sortOrder' => $this->sortOrder ?: 9999,
            'default' => $this->default,
        ];
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'handle' => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
        ];
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['labelHtml', 'uiLabel']);
    }
}
