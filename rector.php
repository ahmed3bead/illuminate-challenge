<?php

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withParallel()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withPreparedSets(
        codeQuality: true,
        earlyReturn: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true
    )
    ->withImportNames()
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ])
    ->withComposerBased(laravel: true)
    ->withSkip([
        DeclareStrictTypesRector::class => [
            __DIR__.'/storage',
            __DIR__.'/bootstrap/cache',
            __DIR__.'/config',
        ],
    ]);
