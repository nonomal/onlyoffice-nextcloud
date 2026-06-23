<?php

declare(strict_types=1);

$finder = Symfony\Component\Finder\Finder::class;

return [
    'prefix' => 'OCA\\Onlyoffice\\Vendor',
    'finders' => [
        $finder::create()
            ->files()
            ->name('*.php')
            ->in('vendor/firebase/php-jwt/src'),
    ],
];
