<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

class ApiDocController extends AbstractController
{
  #[Route('/api/test', name: 'api_test', methods: ['GET'])]
  #[OA\Get(
    path: '/api/test',
    summary: 'Test GET de l\'API',
    description: 'Endpoint de test pour vérifier le fonctionnement de l\'API'
  )]
  #[OA\Response(
    response: 200,
    description: 'Test réussi',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'message', type: 'string', example: 'L\'API EcoRide fonctionne correctement !'),
        new OA\Property(property: 'timestamp', type: 'string', example: '2023-07-18 14:30:00'),
        new OA\Property(property: 'version', type: 'string', example: '1.0.0')
      ]
    )
  )]
  public function test(): JsonResponse
  {
    return $this->json([
      'status' => 'success',
      'message' => 'L\'API EcoRide fonctionne correctement !',
      'timestamp' => date('Y-m-d H:i:s'),
      'version' => '1.0.0'
    ]);
  }

  #[Route('/api/test', name: 'api_test_post', methods: ['POST'])]
  #[OA\Post(
    path: '/api/test',
    summary: 'Test POST de l\'API',
    description: 'Endpoint de test pour vérifier les requêtes POST'
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['message'],
      properties: [
        new OA\Property(property: 'message', type: 'string', description: 'Message de test', example: 'Hello EcoRide!'),
        new OA\Property(property: 'data', type: 'object', description: 'Données optionnelles', nullable: true)
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Test POST réussi',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'message', type: 'string', example: 'POST reçu avec succès'),
        new OA\Property(property: 'received_data', type: 'object'),
        new OA\Property(property: 'timestamp', type: 'string', example: '2023-07-18 14:30:00')
      ]
    )
  )]
  #[OA\Response(
    response: 400,
    description: 'Données invalides'
  )]
  public function testPost(Request $request): JsonResponse
  {
    $contentType = $request->headers->get('Content-Type');
    $content = $request->getContent();

    // Vérifier le Content-Type
    if (!str_contains($contentType, 'application/json')) {
      return $this->json([
        'status' => 'error',
        'message' => 'Content-Type doit être application/json',
        'received_content_type' => $contentType
      ], 400);
    }

    // Décoder le JSON
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->json([
        'status' => 'error',
        'message' => 'Erreur de décodage JSON',
        'json_error' => json_last_error_msg(),
        'content' => $content
      ], 400);
    }

    return $this->json([
      'status' => 'success',
      'message' => 'POST reçu avec succès',
      'received_data' => $data,
      'content_type' => $contentType,
      'timestamp' => date('Y-m-d H:i:s'),
      'method' => $request->getMethod()
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
        '/api/test (GET)' => 'Test GET de l\'API',
        '/api/test (POST)' => 'Test POST de l\'API',
        '/api/health' => 'Status de l\'API',
        '/api/cars' => 'Gestion des véhicules',
        '/api/users' => 'Gestion des utilisateurs',
        '/api/doc' => 'Information sur la documentation',
        '/api/doc.json' => 'Documentation OpenAPI JSON'
      ]
    ]);
  }
}
