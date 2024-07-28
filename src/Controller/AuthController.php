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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Entity\User;
use App\Service\AuthService;

class AuthController extends AbstractController
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

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $response = ['response' => ['authenticated' => false]];
        $cookies = [];
        $received = json_decode($request->getContent(), true);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $received['email']]);

        if ($user) {
            $makedCookies = $this->authService->makeCookies($user);

            // $this->logger->info('makedCookies', ['makedCookies' => $makedCookies]);

            $response['response']['authenticated'] = true;
            $cookies = ['Set-Cookie' => $makedCookies];
        }

        return new JsonResponse($response, Response::HTTP_OK, $cookies);
    }

    #[Route('/auth/check', name: 'auth_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $response = ['response' => ['authenticated' => false]];
        $checkedCookies = $this->authService->checkCookies($request);

        if ($checkedCookies !== false) {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $checkedCookies['email']]);

            if ($user && $user->getCheckAuth() === $checkedCookies['auth']) {
                $response['response'] = ['authenticated' => true, 'email' => $checkedCookies['email']];
            }
        }

        return new JsonResponse($response, Response::HTTP_OK);
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $response = new JsonResponse([
            'response' => ['authenticated' => false]
        ], Response::HTTP_OK);

        foreach ($request->cookies as $name => $value) {
            $response->headers->setCookie(new Cookie($name, '', time() - 3600));
        }

        return $response;
    }

}
