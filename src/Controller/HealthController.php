<?php

namespace App\Controller;

use App\Service\MongoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(private MongoService $mongo)
    {
    }

    #[Route('/api/health/mongo', name: 'api_health_mongo', methods: ['GET'])]
    public function mongo(): JsonResponse
    {
        try {
            $result = $this->mongo->ping();
            // Crée les indexes TTL si besoin
            $this->mongo->ensureIndexes();
            return $this->json([
                'status' => 'ok',
                'ping' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
