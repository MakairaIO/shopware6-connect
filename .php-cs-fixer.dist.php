<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP81Migration' => true,
        '@PHP80Migration:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_summary' => false,
        // Disabled to keep Psalm annotations intact (@see https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/4446)
        'phpdoc_to_comment' => false,
        'single_line_throw' => false,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => ['arrays', 'match', 'parameters'],
        ],
        'use_arrow_functions' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__ . '/src')
    )
    ->setCacheFile('.php-cs-fixer.cache');
