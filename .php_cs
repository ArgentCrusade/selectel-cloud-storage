<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__.'/src');

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules([
        '@PSR2' => true,
        'phpdoc_align' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);
