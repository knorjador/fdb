<?php

namespace App\Service;

class RegexService
{
    private const COMPANY = '/^[a-zA-Z0-9\s]{1,255}$/';

    private const REGEXES = [
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'siret' =>  '/^\d{14}$/',
        'siren' =>  '/^\d{9}$/',
        'name' => self::COMPANY,
        'address' => self::COMPANY,
        'tva' => self::COMPANY
    ];

    public function getRegexes(): array
    {
        return self::REGEXES;
    }

    public function getKey(string $key): string 
    {
        $displays = [
            'email' => 'Email incorrect',
            'siret' => 'N° SIRET incorrect',
            'siren' => 'N° SIREN incorrect',
            'name' => 'Nom incorrect',
            'address' => 'Adresse incorrecte',
            'tva' => 'TVA incorrecte'
        ];

        return $displays[$key];
    }
    
}