<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class RequestLoggerListener
{

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($_ENV['APP_ENV'] === 'dev') {
            $request = $event->getRequest();
            $headers = json_encode($request->headers->all(), JSON_PRETTY_PRINT);
            $cookies = json_encode($request->cookies->all(), JSON_PRETTY_PRINT);
            $body = json_decode($request->getContent(), true);

            $this->logger->info('Headers: ' . PHP_EOL . $headers);
            $this->logger->info('Cookies: ' . PHP_EOL . $cookies);
            $this->logger->info('Body: ', $body ?? []);
        }
    }
    
}

