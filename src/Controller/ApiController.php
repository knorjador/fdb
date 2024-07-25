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
use App\Service\CryptoService;

class ApiController extends AbstractController
{

    private $entityManager;
    private $jwtManager;
    private $cryptoService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, 
        JWTTokenManagerInterface $jwtManager,
        CryptoService $cryptoService,
        LoggerInterface $logger
    )
    {
        $this->entityManager = $entityManager;
        $this->jwtManager = $jwtManager;
        $this->cryptoService = $cryptoService;
        $this->logger = $logger;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'];

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            $bearer = $this->jwtManager->create($user);
            $expiration = $this->jwtManager->parse($bearer)['exp'];

            $encrypted = $this->cryptoService->encrypt(json_encode([
                'email' => $email,
                'expiration' => $expiration,
                'checkAuth' => $user->getCheckAuth()
            ]));
            
            // $this->logger->info('Bearer token generated during login', ['bearer' => $bearer]);
            // $this->logger->info('expiration', ['expiration' => $expiration]);
            // $this->logger->info('encrypted', ['encrypted' => $encrypted]);

            $cookieBearer = $this->makeCookie('bearer', $bearer, $expiration);
            $cookieAuth = $this->makeCookie('auth', $encrypted, $expiration);

            return new JsonResponse([
                'success' => true
            ], Response::HTTP_OK, ['Set-Cookie' => [$cookieBearer, $cookieAuth]]);
        }

        return new JsonResponse(['success' => false], Response::HTTP_OK);
    }

    #[Route('/api/check', name: 'api_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $cookieBearer = $request->cookies->get('bearer');
        $cookieAuth = $request->cookies->get('auth');

        // $this->logger->info('Cookie bearer', ['bearer' => $cookieBearer]);
        // $this->logger->info('Cookie auth', ['auth' => $cookieAuth]);

        if ($cookieBearer !== null && $cookieAuth !== null) {
            $checked = $this->checkCookies($cookieBearer, $cookieAuth);

            if ($checked !== false) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $checked['email']]);
    
                if ($user && $user->getCheckAuth() === $checked['auth']) {
                    return new JsonResponse(['success' => true, 'email' => $checked['email']], Response::HTTP_OK);
                }
            }
        }

        return new JsonResponse(['success' => false], Response::HTTP_OK);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $response = new JsonResponse([
            'success' => true
        ], Response::HTTP_OK);

        foreach ($request->cookies as $name => $value) {
            $response->headers->setCookie(new Cookie($name, '', time() - 3600));
        }

        return $response;
    }

    private function makeCookie(string $name, string $value, string $expiration): string
    {
        return new Cookie(
            $name,
            $value,
            $expiration,
            '/', // Path
            null, // Domain
            true, // Secure (https only)
            true, // HttpOnly, no js
            Cookie::SAMESITE_STRICT // SameSite
        );
    }

    private function checkCookies($bearer, $auth) 
    {
        $parts = explode('.', $bearer); 

        if (count($parts) === 3) {
            $payload = json_decode(base64_decode($parts[1]), true);

            if ($payload['exp'] && $payload['username']) {
                $decrypted = $this->cryptoService->decrypt($auth);

                if ($decrypted !== false) {
                    $decrypted = json_decode($decrypted, true);

                    if ($payload['exp'] === $decrypted['expiration'] && 
                        $payload['username'] === $decrypted['email']
                    ) {
                        return ['email' => $decrypted['email'], 'auth' => $decrypted['checkAuth']];
                    }
                }
            }
        } 

        return false;
    }

}
