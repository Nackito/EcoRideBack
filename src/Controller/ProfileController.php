<?php

namespace App\Controller;

use App\Service\MongoService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
  public function __construct(private MongoService $mongo, private Connection $db) {}

  private function getUserIdFromToken(Request $request): int
  {
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      throw new \RuntimeException('Token manquant');
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      throw new \RuntimeException('Token invalide');
    }
    return (int) $parts[0];
  }

  #[Route('/api/account/profile', name: 'account_profile_get', methods: ['GET'])]
  public function getProfile(Request $request): JsonResponse
  {
    try {
      $userId = $this->getUserIdFromToken($request);

      // Base user from SQL
      $row = $this->db->fetchAssociative('SELECT id, pseudo, email, created_at FROM users WHERE id = :id', ['id' => $userId]);
      if (!$row) {
        return $this->json(['error' => 'Utilisateur non trouvé'], 404);
      }

      // Extras from Mongo
      $col = $this->mongo->getCollection('user_profile_extra');
      $extra = $col->findOne(['userId' => $userId]) ?? [];

      // Normalize Mongo BSON
      $extraArr = json_decode(json_encode($extra), true) ?? [];

      return $this->json([
        'id' => (int) $row['id'],
        'pseudo' => $row['pseudo'] ?? null,
        'email' => $row['email'] ?? null,
        'createdAt' => $row['created_at'] ?? null,
        'firstName' => $extraArr['firstName'] ?? null,
        'lastName' => $extraArr['lastName'] ?? null,
        'phone' => $extraArr['phone'] ?? null,
        'birthDate' => $extraArr['birthDate'] ?? null,
        'bio' => $extraArr['bio'] ?? null,
        'profilePicture' => $extraArr['profilePicture'] ?? null,
      ]);
    } catch (\RuntimeException $e) {
      return $this->json(['error' => $e->getMessage()], 401);
    } catch (\Throwable $e) {
      return $this->json(['error' => 'Erreur lors de la récupération du profil', 'details' => $e->getMessage()], 500);
    }
  }

  #[Route('/api/account/profile', name: 'account_profile_set', methods: ['POST'])]
  public function setProfile(Request $request): JsonResponse
  {
    try {
      $userId = $this->getUserIdFromToken($request);
      $data = json_decode($request->getContent(), true) ?? [];

      $allowedKeys = ['firstName', 'lastName', 'phone', 'birthDate', 'bio', 'profilePicture'];
      $update = [];
      foreach ($allowedKeys as $k) {
        if (array_key_exists($k, $data)) {
          $update[$k] = is_string($data[$k]) ? trim((string)$data[$k]) : $data[$k];
        }
      }

      if (empty($update)) {
        return $this->json(['error' => 'Aucune donnée fournie'], 400);
      }

      $col = $this->mongo->getCollection('user_profile_extra');
      $col->updateOne(
        ['userId' => $userId],
        ['$set' => array_merge($update, [
          'userId' => $userId,
          'updatedAt' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
        ])],
        ['upsert' => true]
      );

      return $this->json(['status' => 'ok']);
    } catch (\RuntimeException $e) {
      return $this->json(['error' => $e->getMessage()], 401);
    } catch (\Throwable $e) {
      return $this->json(['error' => 'Erreur lors de la mise à jour du profil', 'details' => $e->getMessage()], 500);
    }
  }
}
