<?php 

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\RegexService;

class HelpersService
{

    private LoggerInterface $logger;
    private RegexService $regexService;

    /**
     * Constructor HelpersService
     *
     * @param LoggerInterface $logger
     * @param RegexService $regexService
    */
    public function __construct(
        LoggerInterface $logger,
        RegexService $regexService
    ) {
        $this->logger = $logger;
        $this->regexService = $regexService;
    }

    /**
     * Checks and processes received data from the request based on the HTTP method
     *
     * @param Request $request
     * @param array $args
     * @return array|string An array of checked parameters if valid, otherwise a string with the key of the invalid parameter
    */
    public function checkReceived(Request $request, array $args): array|string
    {
        $regexes = $this->regexService->getRegexes();
        $method = $request->getMethod();
        $received = [];
        $checked = [];

        if ($method == 'POST' || $method === 'PUT') {
            $received = json_decode($request->getContent(), true);
        } elseif ($method === 'GET' || $method === 'DELETE') {
            foreach ($args as $arg) {
                $received[$arg] = $request->attributes->get($arg);
            }
        }

        // $this->logger->info('received', ['received' => $received]);

        foreach ($args as $arg) {
            if (
                !isset($received[$arg]) ||
                (isset($regexes[$arg]) && !preg_match($regexes[$arg], $received[$arg]))
            ) {
                return $this->regexService->getKey($arg);
            }

            $checked[$arg] = trim($received[$arg]);
        }

        return $checked;
    }
    
}
