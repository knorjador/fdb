<?php 

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
// ---- PACKAGES ----
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
// ---- DB ----
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
// ---- SERVICES ----
use App\Service\CryptoService;

class AuthService
{

    private $entityManager;
    private $jwtManager;
    private $cryptoService;

    public function __construct(
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager,
        CryptoService $cryptoService
    ) {
        $this->entityManager = $entityManager;
        $this->jwtManager = $jwtManager;
        $this->cryptoService = $cryptoService;
    }

    public function makeCookies(User $user): array
    {
        $bearer = $this->jwtManager->create($user);
        $expiration = $this->jwtManager->parse($bearer)['exp'];
        $encrypted = $this->cryptoService->encrypt(json_encode([
            'email' => $user->getEmail(),
            'checkAuth' => $user->getCheckAuth(),
            'expiration' => $expiration
        ]));

        return [
            $this->makeCookie('bearer', $bearer, $expiration), 
            $this->makeCookie('auth', $encrypted, $expiration)
        ];
    }

    public function checkCookies(Request $request): bool|array
    {
        $bearer = $request->cookies->get('bearer');
        $auth = $request->cookies->get('auth');

        if ($bearer === null || $auth === null) {
            return false;
        }

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

    public function generateCheckAuth(int $length = 32): string
    {
        return bin2hex(openssl_random_pseudo_bytes($length));
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
    
}
