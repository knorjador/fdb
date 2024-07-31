<?php 

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        }

        return $checkedRequest;
    }

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