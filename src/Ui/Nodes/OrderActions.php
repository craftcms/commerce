<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Ui\Nodes;

use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\NodePayload;
use CraftCms\Cms\Ui\UiHtmlRenderer;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction;

use function CraftCms\Cms\t;

/**
 * Handles the order actions that need a settings step, such as
 * {@see DownloadOrderPdfAction}, for the order element indexes on the same screen.
 *
 * Add one per screen, outside any tab, so its popover isn't inside a hidden tab.
 */
class OrderActions implements Node
{
    private function __construct(private readonly string $uid)
    {
    }

    public static function make(string $uid): self
    {
        return new self($uid);
    }

    public static function renderHtml(NodePayload $node, UiPayload $payload, UiHtmlRenderer $renderer): string
    {
        return '';
    }

    public function component(): string
    {
        return 'commerce:order-actions';
    }

    public function uid(): ?string
    {
        return $this->uid;
    }

    /**
     * @return array{labels: array<string, string>}
     */
    public function props(): array
    {
        return [
            'labels' => [
                'collatedPdf' => t('Collated PDF', category: 'commerce'),
                'download' => t('Download', category: 'commerce'),
                'downloadType' => t('Download Type', category: 'commerce'),
                'pdf' => t('PDF', category: 'commerce'),
                'zipFile' => t('ZIP file', category: 'commerce'),
            ],
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
