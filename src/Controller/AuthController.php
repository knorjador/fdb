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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
// ---- DB ----
use App\Repository\UserRepository;
// ---- SERVICES ----
use App\Service\HelpersService;
use App\Service\AuthService;

class AuthController extends AbstractController
{

    private LoggerInterface $logger;
    private UserRepository $userRepository;
    private HelpersService $helpersService;
    private AuthService $authService;

    /**
     * Constructor AuthController
     *
     * @param LoggerInterface $logger
     * @param UserRepository $userRepository
     * @param HelpersService $helpersService
     * @param AuthService $authService
    */
    public function __construct(
        LoggerInterface $logger,
        UserRepository $userRepository,
        HelpersService $helpersService,
        AuthService $authService
    )
    {
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->helpersService = $helpersService;
        $this->authService = $authService;
    }

    /**
     * Check if the user is authenticated based on cookies
     * with method POST 
     * 
     * @param Request $request
     * @return JsonResponse
    */
    #[Route('/auth/check', name: 'auth_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $response = ['response' => ['authenticated' => false]];
        $checkedCookies = $this->authService->checkCookies($request);

        if ($checkedCookies !== false) {
            $user = $this->userRepository->findOneByEmail($checkedCookies['email']);

            if ($user && $user->getCheckAuth() === $checkedCookies['auth']) {
                $response['response'] = ['authenticated' => true, 'email' => $checkedCookies['email']];
            }
        }

        return new JsonResponse($response, Response::HTTP_OK);
    }

    /**
     * Handle user login && generate authentication cookies
     * with method POST
     *
     * @param Request $request
     * @return JsonResponse
    */
    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $response = ['response' => ['authenticated' => false]];
        $cookies = [];
        $received = $this->helpersService->checkReceived($request, ['email']);

        if ($received !== false) {
            $user = $this->userRepository->findOneByEmail($received['email']);

            if ($user) {    
                $makedCookies = $this->authService->makeCookies($user);
                $response['response']['authenticated'] = true;
                $cookies = ['Set-Cookie' => $makedCookies];
            }
        }

        return new JsonResponse($response, Response::HTTP_OK, $cookies);
    }

    /**
     * Handle user logout and clear authentication cookies
     * with method POST
     *
     * @param Request $request
     * @return JsonResponse
    */
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
