<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Form\Nodes;

use CraftCms\Cms\Form\Contracts\Control;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Form\FormPayload;
use CraftCms\Cms\Form\NodePayload;
use Illuminate\Support\Traits\Conditionable;

use function CraftCms\Cms\t;

/**
 * A "Generate" button that batch-creates coupon codes, for use in a
 * {@see \CraftCms\Cms\Form\Nodes\Field::actions()} slot next to a coupons
 * {@see \CraftCms\Cms\Form\Controls\Table}. Generated codes are appended to the
 * table's rows, and the format used is written back to the coupon format value.
 *
 * Commerce-owned, registered by Commerce's own Vite bundle — see
 * {@see UsageCounter} for why.
 */
class CouponGenerator implements Node
{
    use Conditionable;

    /**
     * @param string[] $couponsPath
     * @param string[] $formatPath
     */
    private function __construct(
        private readonly string $uid,
        private readonly string $generateUrl,
        private readonly array $couponsPath,
        private readonly array $formatPath,
    ) {
    }

    /**
     * @param string[] $couponsPath
     * @param string[] $formatPath
     */
    public static function make(string $uid, string $generateUrl, array $couponsPath, array $formatPath): self
    {
        return new self($uid, $generateUrl, $couponsPath, $formatPath);
    }

    /**
     * Generating needs a client-side request that appends to the table's rows,
     * so the JS-less fallback renders nothing.
     */
    public static function renderHtml(NodePayload $node, FormPayload $payload, FormHtmlRenderer $renderer): string
    {
        return '';
    }

    public function component(): string
    {
        return 'commerce:coupon-generator';
    }

    public function uid(): ?string
    {
        return $this->uid;
    }

    /**
     * @return array{
     *     generateUrl: string,
     *     couponsPath: string[],
     *     formatPath: string[],
     *     labels: array<string, string>,
     * }
     */
    public function props(): array
    {
        return [
            'generateUrl' => $this->generateUrl,
            'couponsPath' => $this->couponsPath,
            'formatPath' => $this->formatPath,
            'labels' => [
                'generate' => t('Generate', category: 'commerce'),
                'count' => t('Number of Coupons', category: 'commerce'),
                'format' => t('Generated Coupon Format', category: 'commerce'),
                'formatInstructions' => t('The format used to generate new coupons, e.g. {example}. Any `#` characters will be replaced with a random letter.', [
                    'example' => '`summer_####`',
                ], category: 'commerce'),
                'formatError' => t('Coupon format is required and must contain at least one `#`.', category: 'commerce'),
                'maxUses' => t('Max Uses', category: 'commerce'),
                'submit' => t('Generate', category: 'commerce'),
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
