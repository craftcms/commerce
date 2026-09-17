<?php

declare(strict_types=1);

use CraftCms\Cms\Gql\ArgumentManager;
use CraftCms\Cms\Gql\Handlers\RelatedEntries;
use CraftCms\Commerce\Gql\Handlers\HasProduct;
use CraftCms\Commerce\Gql\Handlers\HasVariant;

it('processes nested relation arguments on hasProduct by delegating to ArgumentManager', function() {
    $relatedEntryIds = [42, 99];

    $argumentManager = new ArgumentManager();
    $argumentManager->setHandler('relatedToEntries', new class($relatedEntryIds) extends RelatedEntries {
        public function __construct(private readonly array $ids)
        {
        }

        #[\Override]
        protected function getIds(string $elementType, array $criteriaList = []): array
        {
            return [$this->ids];
        }
    });
    $argumentManager->setHandler('hasProduct', new HasProduct());

    $result = $argumentManager->prepareArguments([
        'hasProduct' => [
            'relatedToEntries' => [['section' => 'news']],
        ],
    ]);

    expect($result['hasProduct'])->toBe([
        'relatedTo' => ['and', ['element' => $relatedEntryIds]],
    ]);
});

it('passes standard, non-relation arguments through hasProduct unchanged', function() {
    $argumentManager = new ArgumentManager();
    $argumentManager->setHandler('hasProduct', new HasProduct());

    $result = $argumentManager->prepareArguments([
        'hasProduct' => [
            'slug' => 'rad-hoodie',
            'type' => 'hoodies',
        ],
    ]);

    expect($result['hasProduct'])->toBe([
        'slug' => 'rad-hoodie',
        'type' => 'hoodies',
    ]);
});

it('processes nested relation arguments on hasVariant by delegating to ArgumentManager', function() {
    $relatedEntryIds = [7, 13];

    $argumentManager = new ArgumentManager();
    $argumentManager->setHandler('relatedToEntries', new class($relatedEntryIds) extends RelatedEntries {
        public function __construct(private readonly array $ids)
        {
        }

        #[\Override]
        protected function getIds(string $elementType, array $criteriaList = []): array
        {
            return [$this->ids];
        }
    });
    $argumentManager->setHandler('hasVariant', new HasVariant());

    $result = $argumentManager->prepareArguments([
        'hasVariant' => [
            'relatedToEntries' => [['section' => 'news']],
        ],
    ]);

    expect($result['hasVariant'])->toBe([
        'relatedTo' => ['and', ['element' => $relatedEntryIds]],
    ]);
});

it('passes standard, non-relation arguments through hasVariant unchanged', function() {
    $argumentManager = new ArgumentManager();
    $argumentManager->setHandler('hasVariant', new HasVariant());

    $result = $argumentManager->prepareArguments([
        'hasVariant' => [
            'sku' => 'hct-blue',
        ],
    ]);

    expect($result['hasVariant'])->toBe([
        'sku' => 'hct-blue',
    ]);
});
