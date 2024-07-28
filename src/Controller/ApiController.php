<?php 

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Company;
use App\Service\AuthService;

class ApiController extends AbstractController
{

    private $entityManager;
    private $authService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthService $authService,
        LoggerInterface $logger
    )
    {
        $this->entityManager = $entityManager;
        $this->authService = $authService;
        $this->logger = $logger;
    }

    #[Route('/api/up', name: 'api_up', methods: ['POST'])]
    public function up(): JsonResponse
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
            return new JsonResponse(['up' => true], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['up' => false], 500);
        }
    }

    #[Route('/api/add', name: 'api_add', methods: ['POST'])]
    public function test(Request $request): JsonResponse
    {
        $checkedCookies = $this->authService->checkCookies($request);

        if ($checkedCookies !== false) {
            // siret service
            return new JsonResponse(['message' => 'You have access to this protected endpoint.'], 200);
        }

        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

}
