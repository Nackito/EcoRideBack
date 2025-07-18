<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiDocController extends AbstractController
{
  #[Route('/api/test', name: 'api_test', methods: ['GET'])]
  public function test(): JsonResponse
  {
    return $this->json([
      'status' => 'success',
      'message' => 'L\'API EcoRide fonctionne correctement !',
      'timestamp' => date('Y-m-d H:i:s'),
      'version' => '1.0.0'
    ]);
  }

  #[Route('/api/health', name: 'api_health', methods: ['GET'])]
  public function health(): JsonResponse
  {
    return $this->json([
      'status' => 'healthy',
      'timestamp' => date('Y-m-d H:i:s'),
      'services' => [
        'database' => 'connected',
        'api' => 'running'
      ]
    ]);
  }

  #[Route('/', name: 'app_home', methods: ['GET'])]
  public function home(): JsonResponse
  {
    return $this->json([
      'message' => 'Bienvenue sur l\'API EcoRide!',
      'version' => '1.0.0',
      'documentation' => '/api/doc.json',
      'endpoints' => [
        '/' => 'Page d\'accueil',
        '/api/test' => 'Test de l\'API',
        '/api/health' => 'Status de l\'API',
        '/api/doc' => 'Information sur la documentation',
        '/api/doc.json' => 'Documentation OpenAPI JSON'
      ]
    ]);
  }
}
