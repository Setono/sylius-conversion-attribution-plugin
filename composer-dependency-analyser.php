<?php

use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\TagBagInterface;
use Setono\TagBagBundle\SetonoTagBagBundle;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreUnknownClasses([SetonoTagBagBundle::class, TagBagInterface::class, InlineScriptTag::class])
    // The only symbol used from this package is the global trigger_deprecation() function. Whether
    // the analyser attributes it to the package (and thus whether the "unused" error fires) depends
    // on the installed dependency set, so ignore the error and don't fail when the ignore is unused.
    ->ignoreErrorsOnPackage('symfony/deprecation-contracts', [ErrorType::UNUSED_DEPENDENCY])
    ->disableReportingUnmatchedIgnores()
;
