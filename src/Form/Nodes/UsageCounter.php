<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Form\Nodes;

use CraftCms\Cms\Form\Contracts\Control;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Form\FormPayload;
use CraftCms\Cms\Form\NodePayload;
use CraftCms\Cms\Support\Html;
use Illuminate\Support\Traits\Conditionable;

/**
 * A read-only usage count alongside a confirm-then-reset button, for use in a
 * {@see Field::actions()} slot next to the limit it's tracking usage against.
 *
 * Commerce-owned (not a `cms`-shared Form Node) — its Vue counterpart is
 * registered by Commerce's own Vite bundle via `window.Cp.$components.register()`,
 * not `cms`'s `register.ts`. See `commerce/resources/js/cp.ts`.
 *
 *     Field::make(t('Limit'), Number::make('limit'))
 *         ->actions(UsageCounter::make(
 *             'limit-usage',
 *             t('{uses} uses across {users} users', ['uses' => 12, 'users' => 4]),
 *             action([Controller::class, 'resetUsage']),
 *             ['id' => $id, 'type' => 'total'],
 *             t('Reset usage'),
 *         )->confirmMessage(t('Are you sure you want to clear this usage counter?')));
 */
class UsageCounter implements Node
{
    use Conditionable;

    private ?string $confirmMessage = null;

    /** @param array<string, mixed> $resetBody */
    private function __construct(
        private readonly string $uid,
        private readonly string $label,
        private readonly string $resetUrl,
        private readonly array $resetBody,
        private readonly string $resetLabel,
    ) {
    }

    /** @param array<string, mixed> $resetBody */
    public static function make(string $uid, string $label, string $resetUrl, array $resetBody, string $resetLabel): self
    {
        return new self($uid, $label, $resetUrl, $resetBody, $resetLabel);
    }

    public function confirmMessage(string $message): static
    {
        $this->confirmMessage = $message;

        return $this;
    }

    /**
     * No sensible plain-HTML equivalent for a client-side confirm + POST +
     * form-refresh (same reasoning already applied to `cms`'s `Table` Form
     * Node's reorder/delete/bulk-actions/search) — the JS-less fallback shows
     * the count with no reset control.
     */
    public static function renderHtml(NodePayload $node, FormPayload $payload, FormHtmlRenderer $renderer): string
    {
        return Html::tag('span', Html::encode($node->props['label']), [
            'class' => ['text-sm', 'text-neutral-text-quiet'],
            'data-form-node' => $node->uid,
        ]);
    }

    public function component(): string
    {
        return 'commerce:usage-counter';
    }

    public function uid(): ?string
    {
        return $this->uid;
    }

    public function props(): array
    {
        return [
            'label' => $this->label,
            'resetUrl' => $this->resetUrl,
            'resetBody' => $this->resetBody,
            'resetLabel' => $this->resetLabel,
            'confirmMessage' => $this->confirmMessage,
        ];
    }

    public function getControl(): ?Control
    {
        return null;
    }

    public function children(): array
    {
        return [];
    }
}
