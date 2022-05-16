<?php
// rector.php
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function(ContainerConfigurator $containerConfigurator): void {
//    $containerConfigurator->import(SetList::CODE_QUALITY);

// register single rule
    $services = $containerConfigurator->services();
    //    $containerConfigurator->import(SetList::PHP_80);
    $services->set(\Rector\Php80\Rector\FunctionLike\UnionTypesRector::class);
    $services->set(\Rector\Php80\Rector\NotIdentical\StrContainsRector::class);
    $services->set(\Rector\Php80\Rector\Identical\StrStartsWithRector::class);
    $services->set(\Rector\Php80\Rector\Identical\StrEndsWithRector::class);
    $services->set(\Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector::class);
};