<?php 

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// ---- PACKAGES ----
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
// ---- DB ----
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\Company;
use App\Repository\CompanyRepository;
// ---- SERVICES ----
use App\Service\HelpersService;
use App\Service\AuthService;
use App\Service\InseeApiService;

class ApiController extends AbstractController
{

    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private CompanyRepository $companyRepository;
    private HelpersService $helpersService;
    private AuthService $authService;
    private InseeApiService $inseeApiService;

    /**
     * Constructor ApiController
     *
     * @param LoggerInterface $logger
     * @param EntityManagerInterface $entityManager
     * @param UserRepository $userRepository
     * @param CompanyRepository $companyRepository
     * @param HelpersService $helpersService
     * @param AuthService $authService
     * @param InseeApiService $inseeApiService
    */
    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        CompanyRepository $companyRepository,
        HelpersService $helpersService,
        AuthService $authService,
        InseeApiService $inseeApiService,
    )
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->companyRepository = $companyRepository;
        $this->helpersService = $helpersService;
        $this->authService = $authService;
        $this->inseeApiService = $inseeApiService;    
    }


    #[OA\Tag(name: 'Company')]
    #[OA\Response(
        response: 200,
        description: 'API is available',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'up',
                            type: 'boolean',
                            example: true
                        )
                    ]
                )
            ]
        )
    )]
    /**
     * Check if the API is available
     *
     * @return JsonResponse
    */
    #[Route('/api/company/up', name: 'api_up', methods: ['POST'])]
    public function up(): JsonResponse
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');

            return new JsonResponse(['response' => ['up' => true]], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['response' => ['up' => false]], Response::HTTP_OK);
        }
    }


    #[OA\Tag(name: 'Company')]
    #[OA\CookieParameter(
        name: 'bearer',
        description: 'Bearer JWT token for authentication',
        required: true,
        allowEmptyValue: true
    )]
    #[OA\CookieParameter(
        name: 'auth',
        description: 'Token for authentication',
        required: true,
        allowEmptyValue: true
    )]
    #[OA\RequestBody(
        description: 'Company data to be created',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'siret',
                    type: 'string',
                    description: 'SIRET number of the company',
                    example: '12345678900001'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Company successfully created or if the company already exists',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'payload',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'severity',
                                    type: 'string',
                                    example: 'success'
                                ),
                                new OA\Property(
                                    property: 'summary',
                                    type: 'string',
                                    example: 'Création'
                                ),
                                new OA\Property(
                                    property: 'detail',
                                    type: 'string',
                                    example: 'Company data was created successfully'
                                )
                            ]
                        )
                    ]
                )
            ]
        )
    )]
    /**
     * Create a new company
     *
     * @param Request $request
     * @return JsonResponse
    */
    #[Route('/api/company/create', name: 'api_company_create', methods: ['POST'])]
    public function createCompany(Request $request): JsonResponse
    {
        $checkedRequest = $this->checkRequest($request, ['siret']);
        $response = ['payload' => $checkedRequest['payload']];

        if (!array_key_exists('fail', $checkedRequest)) {
            $user = $checkedRequest['user'];
            $siret = $checkedRequest['payload']['siret'];
            $existsCompany = $this->companyRepository->existsCompanyForUser($user, $siret);

            if ($existsCompany === false) {
                $company = $this->inseeApiService->getCompanyData($siret);

                if ($company['status'] === 200) {
                    $saved = $this->companyRepository->save($user, $company['payload']);

                    $response['payload'] = $this->buildPayload([
                        'action' => 'Création',
                        'success' => $saved !== false,
                        'company' => $saved
                    ]);
                } else {
                    $response['payload'] = $company['payload'];
                }
            } else {
                $response['payload'] = [
                    'severity' => 'info',
                    'summary' => 'Info',
                    'detail' => $existsCompany->getName() . '\n  N° SIRET : ' . $siret . '\n Ajouté précédemment'
                ];
            }
        }

        return new JsonResponse(['response' => $response], $checkedRequest['status']);  
    }


    #[OA\Tag(name: 'Company')]
    #[OA\CookieParameter(
        name: 'bearer',
        description: 'Bearer JWT token for authentication',
        required: true
    )]
    #[OA\CookieParameter(
        name: 'auth',
        description: 'Token for authentication',
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'List of user\'s companies or redirect if authentication fails',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'companies',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'siret',
                                        type: 'string',
                                        example: '12345678900001'
                                    ),
                                    new OA\Property(
                                        property: 'siren',
                                        type: 'string',
                                        example: '123456789'
                                    ),
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        example: 'Company Name'
                                    ),
                                    new OA\Property(
                                        property: 'address',
                                        type: 'string',
                                        example: '1234 Street Name'
                                    ),
                                    new OA\Property(
                                        property: 'tva',
                                        type: 'string',
                                        example: 'FR123456789'
                                    )
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'redirect',
                            type: 'boolean',
                            example: false
                        )
                    ]
                )
            ]
        )
    )]
    /**
     * Read companies for an user
     *
     * @param Request $request
     * @return JsonResponse
    */
    #[Route('/api/company/read', name: 'api_company_read', methods: ['GET'])]
    public function readCompanies(Request $request): JsonResponse
    {
        $checkedRequest = $this->checkRequest($request, false);

        if (!array_key_exists('fail', $checkedRequest)) {
            $companies = $this->userRepository->findUserCompanies($checkedRequest['user']);

            return new JsonResponse(
                ['response' => [
                    'companies' => array_reverse($companies)],
                    'redirect' => false
                ],
                $checkedRequest['status']
            ); 
        }

        $response = new JsonResponse([
            'response' => ['redirect' => true]
        ], Response::HTTP_OK);

        foreach ($request->cookies as $name => $value) {
            $response->headers->setCookie(new Cookie($name, '', time() - 3600));
        }

        return $response;
    }

    #[OA\Tag(name: 'Company')]
    #[OA\CookieParameter(
        name: 'bearer',
        description: 'Bearer JWT token for authentication',
        required: true
    )]
    #[OA\CookieParameter(
        name: 'auth',
        description: 'Token for authentication',
        required: true
    )]
    #[OA\RequestBody(
        description: 'Company data to be updated',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'siret',
                    type: 'string',
                    description: 'SIRET number of the company',
                    example: '12345678900001'
                ),
                new OA\Property(
                    property: 'siren',
                    type: 'string',
                    description: 'SIREN number of the company',
                    example: '123456789'
                ),
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    description: 'Name of the company',
                    example: 'New Name'
                ),
                new OA\Property(
                    property: 'address',
                    type: 'string',
                    description: 'Address of the company',
                    example: 'New Address'
                ),
                new OA\Property(
                    property: 'tva',
                    type: 'string',
                    description: 'VAT number of the company',
                    example: 'New TVA'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Company successfully updated',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'payload',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'severity',
                                    type: 'string',
                                    example: 'success'
                                ),
                                new OA\Property(
                                    property: 'summary',
                                    type: 'string',
                                    example: 'Modification'
                                ),
                                new OA\Property(
                                    property: 'detail',
                                    type: 'string',
                                    example: 'Company data was updated successfully'
                                )
                            ]
                        )
                    ]
                )
            ]
        )
    )]
    /**
     * Update a company
     *
     * @param Request $request
     * @return JsonResponse
    */
    #[Route('/api/company/update', name: 'api_company_update', methods: ['PUT'])]
    public function updateCompany(Request $request): JsonResponse
    {
        $checkedRequest = $this->checkRequest($request, ['siret', 'siren', 'name', 'address', 'tva']);
        $response = ['payload' => $checkedRequest['payload'], 'fail' => true];

        if (!array_key_exists('fail', $checkedRequest)) {
            $response['fail'] = false;
            $updated = $this->companyRepository->update(
                $checkedRequest['user'], 
                $checkedRequest['payload']
            );

            if ($updated['fail'] === false && $updated['modified'] === false) {
                $response['payload'] = [];
            } else {
                $response['payload'] = $this->buildPayload([
                    'action' => 'Modification',
                    'success' => $updated['fail'] !== true,
                    'company' => $updated['company']
                ]); 
            }
        }

        return new JsonResponse(['response' => $response], $checkedRequest['status']);
    }

    #[OA\Tag(name: 'Company')]
    #[OA\CookieParameter(
        name: 'bearer',
        description: 'Bearer JWT token for authentication',
        required: true
    )]
    #[OA\CookieParameter(
        name: 'auth',
        description: 'Token for authentication',
        required: true
    )]
    #[OA\Parameter(
        name: 'siret',
        in: 'path',
        required: true,
        description: 'SIRET number of the company to be deleted',
        schema: new OA\Schema(
            type: 'string',
            example: '12345678900001'
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Company successfully deleted',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'payload',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'severity',
                                    type: 'string',
                                    example: 'success'
                                ),
                                new OA\Property(
                                    property: 'summary',
                                    type: 'string',
                                    example: 'Suppression'
                                ),
                                new OA\Property(
                                    property: 'detail',
                                    type: 'string',
                                    example: 'Company data was deleted successfully'
                                )
                            ]
                        )
                    ]
                )
            ]
        )
    )]
    /**
     * Delete a company
     *
     * @param Request $request
     * @param string $siret
     * @return JsonResponse
    */
    #[Route('/api/company/delete/{siret}', name: 'api_company_delete', methods: ['DELETE'])]
    public function deleteCompany(Request $request, string $siret): JsonResponse
    {
        $checkedRequest = $this->checkRequest($request, ['siret']);
        $response = ['payload' => $checkedRequest['payload']];

        if (!array_key_exists('fail', $checkedRequest)) {
            $deleted = $this->companyRepository->deleteCompanyBySiret(
                $checkedRequest['user'],
                $checkedRequest['payload']['siret']
            );
            $response['payload'] = $this->buildPayload([
                'action' => 'Suppression',
                'success' => $deleted !== false,
                'company' => $deleted
            ]);
        }

        return new JsonResponse(['response' => $response], $checkedRequest['status']);     
    }

    /**
     * Check the request authentification
     *
     * @param Request $request
     * @param array|bool $received
     * @return array
    */
    private function checkRequest(Request $request, array|bool $received): array
    {
        $checkedRequest = ['status' => Response::HTTP_UNAUTHORIZED];
        $checkedCookies = $this->authService->checkCookies($request);

        if ($checkedCookies !== false) {
            $user = $this->userRepository->findOneByEmail($checkedCookies['email']);

            if ($user && $user->getCheckAuth() === $checkedCookies['auth']) {
                $checkedRequest['status'] = Response::HTTP_OK;
                $payload = is_array($received)
                    ? $this->helpersService->checkReceived($request, $received)
                    : [];

                if (is_array($payload)) {
                    $checkedRequest['user'] = $user;
                    $checkedRequest['payload'] = $payload;
                } else {
                    $checkedRequest['fail'] = true;
                    $checkedRequest['payload'] = [
                        'severity' => 'error',
                        'summary' => 'Erreur',
                        'detail' => $payload
                    ];
                }
            }
        } else {
            $checkedRequest['fail'] = true;
            $checkedRequest['payload'] = [];
        }

        return $checkedRequest;
    }

    /**
     * Build the payload sended to front end
     *
     * @param array $params
     * @return array
    */
    private function buildPayload(array $params): array
    {
        $payload = [];
        
        if ($params['success']) {
            $payload = [
                'severity' => 'success',
                'summary' => 'Succès',
                'detail' => $params['action'] . '\n' . 
                    $params['company']->getName() . 
                    '\n  N° SIRET : ' . $params['company']->getSiret()
            ];
        } else {
            $payload = [
                'severity' => 'error',
                'summary' => 'Erreur',
                'detail' => 'Désolé, une erreur est survenue lors de la ' .strtolower($params['action'])
            ];
        }

        return $payload;
    }

}