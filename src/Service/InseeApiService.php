<?php 

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/*

limitations 
https://api.gouv.fr/guides/quelle-api-sirene
30 appels par minute

codes http 
https://www.sirene.fr/static-resources/htm/codes_retour_311.html

*/

class InseeApiService
{

    const BASE_URL = 'https://api.insee.fr/';
    const API_VERSION = 'V3.11';

    private $httpClient;
    private $cache;
    private $pubKey;
    private $privateKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        FilesystemAdapter $cache,
        string $pubKey, 
        string $privateKey,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->pubKey = $pubKey;
        $this->privateKey = $privateKey;
        $this->logger = $logger;
    }

    public static function tokenUrl(): string
    {
        return self::BASE_URL . 'token';
    }

    public static function siretUrl(): string
    {
        return self::BASE_URL . 'entreprises/sirene/' . self::API_VERSION . '/siret/';
    }

    public function getBearer(): string
    {
        return $this->cache->get('insee_api_bearer', function (ItemInterface $item) {
            $response = $this->httpClient->request('POST', self::tokenUrl(), [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->pubKey . ':' . $this->privateKey),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => 'grant_type=client_credentials'
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === Response::HTTP_OK) {
                $data = $response->toArray();
                $item->expiresAfter($data['expires_in'] - 60);

                $this->logger->info('insee_api_bearer', ['insee_api_bearer' => $data['access_token']]);
                $this->logger->info('expires_in', ['expires_in' => $data['expires_in']]);

                return $data['access_token'];
            }

            throw new \RuntimeException('Unable to fetch bearer token');
        });
    }

    public function getCompanyData(string $siret): array
    {
        try {
            $response = $this->httpClient->request('GET', self::siretUrl() . $siret, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getBearer(),
                    'Content-Type' => 'application/json'
                ]
            ]);

            // $this->logger->info('insee', ['insee' => $response]);

            $status = $response->getStatusCode();

            switch ($status) {
                case 200:
                    return ['status' => 200, 'payload' => $this->formatCompany($siret, $response->toArray())];
                case 401:
                    $this->cache->delete('insee_api_bearer');
                    return $this->getCompanyData($siret);
                case 429:
                    return [
                        'status' => 429, 
                        'payload' => [
                            'severity' => 'info',
                            'summary' => 'Info',
                            'detail' => 'Réessayez dans quelques instants'
                        ]
                    ];
                case 403:
                case 404:
                case 500:
                case 503:
                default:
                    return [
                        'status' => 503, 
                        'payload' => [
                            'severity' => 'error',
                            'summary' => 'Erreur',
                            'detail' => 'Désolé, une erreur est survenue'
                        ]
                    ];
            }
        } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            return [
                'status' => 503, 
                'payload' => [
                    'severity' => 'error',
                    'summary' => 'Erreur',
                    'detail' => 'Désolé, une erreur est survenue'
                ]
            ];
        }
    }

    private function formatCompany(string $siret, array $company): array
    {
        $name = '';
        $address = '';
        $siren = '';
        $tva = '';

        if (isset($company['etablissement'])) {
            $etablissement = $company['etablissement'];
            $siren = $etablissement['siren'] ?? '';

            if (isset($etablissement['uniteLegale'])) {
                $uniteLegale = $etablissement['uniteLegale'];
                $name = $uniteLegale['denominationUniteLegale'] ?? '';
                $tva = $uniteLegale['identifiantAssociationUniteLegale'] ?? '';
            }

            if (isset($etablissement['adresseEtablissement'])) {
                $adresse = $etablissement['adresseEtablissement'];
                $address = ($adresse['numeroVoieEtablissement'] ?? '') . ' ' .
                           ($adresse['typeVoieEtablissement'] ?? '') . ' ' .
                           ($adresse['libelleVoieEtablissement'] ?? '') . ' ' .
                           ($adresse['codePostalEtablissement'] ?? '') . ' ' .
                           ($adresse['libelleCommuneEtablissement'] ?? '');
            }
        }

        return [
            'siret' => $siret,
            'name' => $name,
            'address' => $address,
            'siren' => $siren,
            'tva' => $tva
        ];
    }

}
