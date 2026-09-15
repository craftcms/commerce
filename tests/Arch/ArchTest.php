<?php

declare(strict_types=1);

arch('No debug functions')
    ->expect('src')
    ->not->toUse(['die', 'dd', 'dump', 'env']);

arch('src/ should not reference legacy Craft core classes (craft\commerce is allowed during the migration)')
    ->expect('src')
    ->not->toUse('craft')
    ->ignoring('craft\commerce');
