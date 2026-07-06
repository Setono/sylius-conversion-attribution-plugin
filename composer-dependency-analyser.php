<?php

use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\TagBagInterface;
use Setono\TagBagBundle\SetonoTagBagBundle;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreUnknownClasses([SetonoTagBagBundle::class, TagBagInterface::class, InlineScriptTag::class])
    // Only symbol used from this package is the global trigger_deprecation() function, which the
    // analyser does not attribute to a package, so it is reported as unused. It is genuinely used.
    ->ignoreErrorsOnPackage('symfony/deprecation-contracts', [ErrorType::UNUSED_DEPENDENCY])
;
