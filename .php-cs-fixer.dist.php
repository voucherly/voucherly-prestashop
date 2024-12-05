<?php

$config = new PrestaShop\CodingStandards\CsFixer\Config();

/** @var \Symfony\Component\Finder\Finder $finder */
$finder = $config
    ->setUsingCache(true)
    ->setRules([
        // Rulesets
        '@PSR2' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP56Migration:risky' => true,
        '@PHPUnit57Migration:risky' => true,

        // PrestaShop
        "no_trailing_whitespace_in_comment" => true,
        "no_blank_lines_after_phpdoc" => true
    ])
    ->getFinder();
    
$finder->in(__DIR__)->exclude('vendor');

return $config;
