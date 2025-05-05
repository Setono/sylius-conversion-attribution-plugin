<?php

use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\TagBagInterface;
use Setono\TagBagBundle\SetonoTagBagBundle;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreUnknownClasses([SetonoTagBagBundle::class, TagBagInterface::class, InlineScriptTag::class])
;
