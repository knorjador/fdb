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

    /**
     * Returns the regular expressions used for validation
     *
     * @return array An associative array where keys are validation types && values are regular expressions
    */
    public function getRegexes(): array
    {
        return self::REGEXES;
    }

    /**
     * Returns the display message for a given validation key
     *
     * @param string $key
     * @return string The display message corresponding to the validation key
    */
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