<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_users_')]
class UserController extends AbstractController
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UserRepository $userRepository,
    private ValidatorInterface $validator,
    private SerializerInterface $serializer,
    private UserPasswordHasherInterface $passwordHasher,
    private Connection $db
  ) {}

  #[Route('/login', name: 'login', methods: ['POST'])]
  #[OA\Post(
    path: '/api/login',
    summary: 'Connexion utilisateur',
    description: 'Authentifie un utilisateur avec son email/pseudo et mot de passe',
    tags: ['User']
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['Email', 'Password'],
      properties: [
        new OA\Property(property: 'Email', type: 'string', description: 'Email de l\'utilisateur', example: 'user@example.com'),
        new OA\Property(property: 'Password', type: 'string', description: 'Mot de passe', example: 'password123')
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Connexion réussie',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(
          property: 'user',
          type: 'object',
          properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'pseudo', type: 'string', example: 'john_doe'),
            new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
            new OA\Property(property: 'firstName', type: 'string', example: 'John'),
            new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'))
          ]
        ),
        new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie'),
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')
      ]
    )
  )]
  #[OA\Response(
    response: 401,
    description: 'Identifiants invalides'
  )]
  #[OA\Response(
    response: 400,
    description: 'Données invalides'
  )]
  public function login(Request $request): JsonResponse
  {
    $data = json_decode($request->getContent(), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->json([
        'error' => 'Données JSON invalides',
        'details' => json_last_error_msg()
      ], Response::HTTP_BAD_REQUEST);
    }

    // Vérifier les champs requis
    if (!isset($data['Email']) || !isset($data['Password'])) {
      return $this->json(['error' => 'Email et Password sont requis'], Response::HTTP_BAD_REQUEST);
    }

    try {
      $email = trim($data['Email']);
      $password = $data['Password'];

      // Recherche dans la table `users` (schéma existant)
      $sql = 'SELECT * FROM users WHERE email = :identifier OR pseudo = :identifier LIMIT 1';
      $row = $this->db->fetchAssociative($sql, ['identifier' => $email]);

      if (!$row) {
        return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_UNAUTHORIZED);
      }

      // Vérifier le mot de passe haché stocké
      if (!isset($row['password']) || !\password_verify($password, $row['password'])) {
        return $this->json(['error' => 'Mot de passe incorrect'], Response::HTTP_UNAUTHORIZED);
      }

      // Générer un token simple
      $token = base64_encode($row['id'] . ':' . time() . ':' . uniqid());

      return $this->json([
        'user' => [
          'id' => (int) $row['id'],
          'pseudo' => $row['pseudo'],
          'email' => $row['email'],
          // Adapter au schéma existant: pas de firstName/lastName
          'roles' => [$row['role'] ?? 'user'],
          'createdAt' => $row['created_at'] ?? null,
        ],
        'message' => 'Connexion réussie',
        'token' => $token
      ], Response::HTTP_OK);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la connexion',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/registration', name: 'registration', methods: ['POST'])]
  #[OA\Post(
    path: '/api/registration',
    summary: 'Créer un nouvel utilisateur',
    description: 'Ajoute un nouvel utilisateur à la base de données',
    tags: ['User']
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['pseudo', 'email', 'password'],
      properties: [
        new OA\Property(property: 'pseudo', type: 'string', description: 'Pseudo de l\'utilisateur', example: 'john_doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Adresse email', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', description: 'Mot de passe', example: 'password123'),
        new OA\Property(
          property: 'roles',
          type: 'array',
          items: new OA\Items(type: 'string', enum: ['ROLE_USER', 'ROLE_DRIVER', 'ROLE_PASSENGER']),
          description: 'Rôles de l\'utilisateur',
          example: ['ROLE_USER', 'ROLE_DRIVER']
        )
      ]
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'Utilisateur créé avec succès',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'pseudo', type: 'string', example: 'john_doe'),
        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'message', type: 'string', example: 'Inscription réussie. Vous pouvez maintenant compléter votre profil.')
      ]
    )
  )]
  #[OA\Response(
    response: 400,
    description: 'Données invalides'
  )]
  public function register(Request $request): JsonResponse
  {
    // Vérifier le Content-Type
    if (!$request->headers->contains('Content-Type', 'application/json')) {
      return $this->json([
        'error' => 'Content-Type doit être application/json',
        'received' => $request->headers->get('Content-Type')
      ], Response::HTTP_BAD_REQUEST);
    }

    $content = $request->getContent();
    if (empty($content)) {
      return $this->json(['error' => 'Corps de la requête vide'], Response::HTTP_BAD_REQUEST);
    }

    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->json([
        'error' => 'Données JSON invalides',
        'details' => json_last_error_msg()
      ], Response::HTTP_BAD_REQUEST);
    }

    // Vérifier les champs requis
    $requiredFields = ['pseudo', 'email', 'password'];
    foreach ($requiredFields as $field) {
      if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
      }
    }

    // Vérifier unicité via DBAL sur la table `users`
    $existingByEmail = $this->db->fetchOne('SELECT COUNT(1) FROM users WHERE email = :email', ['email' => $data['email']]);
    if ((int)$existingByEmail > 0) {
      return $this->json(['error' => 'Un utilisateur avec cette adresse email existe déjà'], Response::HTTP_CONFLICT);
    }

    $existingByPseudo = $this->db->fetchOne('SELECT COUNT(1) FROM users WHERE pseudo = :pseudo', ['pseudo' => $data['pseudo']]);
    if ((int)$existingByPseudo > 0) {
      return $this->json(['error' => 'Un utilisateur avec ce pseudo existe déjà'], Response::HTTP_CONFLICT);
    }

    try {
      // Hachage du mot de passe (compatible password_verify)
      $hashedPassword = \password_hash($data['password'], PASSWORD_DEFAULT);

      // Adapter les rôles front aux rôles SQL (user/employee/admin). Par défaut: 'user'
      $roleSql = 'user';
      if (isset($data['roles']) && is_array($data['roles'])) {
        if (in_array('ROLE_ADMIN', $data['roles'], true)) {
          $roleSql = 'admin';
        } elseif (in_array('ROLE_EMPLOYED', $data['roles'], true)) {
          $roleSql = 'employee';
        }
      }

      // Insertion dans la table `users`
      $this->db->insert('users', [
        'pseudo' => $data['pseudo'],
        'email' => $data['email'],
        'password' => $hashedPassword,
        'role' => $roleSql,
        'credits' => 20,
        'is_suspended' => 0,
        // created_at est par défaut CURRENT_TIMESTAMP
      ]);

      // Récupérer l'utilisateur créé
      $userId = (int) $this->db->lastInsertId();
      $row = $this->db->fetchAssociative('SELECT * FROM users WHERE id = :id', ['id' => $userId]);

      return $this->json([
        'id' => $userId,
        'pseudo' => $row['pseudo'] ?? $data['pseudo'],
        'email' => $row['email'] ?? $data['email'],
        'roles' => [$row['role'] ?? 'user'],
        'createdAt' => $row['created_at'] ?? null,
        'message' => 'Inscription réussie. Vous pouvez maintenant compléter votre profil.'
      ], Response::HTTP_CREATED);
    } catch (\Doctrine\DBAL\Exception $e) {
      return $this->json([
        'error' => 'Erreur SQL lors de la création de l\'utilisateur',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la création de l\'utilisateur',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/{id}/change-password', name: 'change_password', methods: ['PATCH'])]
  #[OA\Patch(
    path: '/api/users/{id}/change-password',
    summary: 'Changer le mot de passe d\'un utilisateur',
    description: 'Met à jour le mot de passe d\'un utilisateur',
    tags: ['User']
  )]
  #[OA\Parameter(
    name: 'id',
    in: 'path',
    description: 'ID de l\'utilisateur',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
      required: ['currentPassword', 'newPassword'],
      properties: [
        new OA\Property(property: 'currentPassword', type: 'string', description: 'Mot de passe actuel'),
        new OA\Property(property: 'newPassword', type: 'string', description: 'Nouveau mot de passe')
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Mot de passe modifié avec succès'
  )]
  #[OA\Response(
    response: 400,
    description: 'Mot de passe actuel incorrect'
  )]
  #[OA\Response(
    response: 404,
    description: 'Utilisateur non trouvé'
  )]
  public function changePassword(int $id, Request $request): JsonResponse
  {
    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true);

    if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
      return $this->json(['error' => 'Les champs currentPassword et newPassword sont requis'], Response::HTTP_BAD_REQUEST);
    }

    // Vérifier le mot de passe actuel
    if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
      return $this->json(['error' => 'Mot de passe actuel incorrect'], Response::HTTP_BAD_REQUEST);
    }

    try {
      // Hasher le nouveau mot de passe
      $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
      $user->setPassword($hashedPassword);

      $this->entityManager->flush();

      return $this->json(['message' => 'Mot de passe modifié avec succès']);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la modification du mot de passe',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
  #[OA\Delete(
    path: '/api/users/{id}',
    summary: 'Supprimer un utilisateur',
    description: 'Supprime un utilisateur de la base de données',
    tags: ['User'],
  )]
  #[OA\Parameter(
    name: 'id',
    in: 'path',
    description: 'ID de l\'utilisateur',
    required: true,
    schema: new OA\Schema(type: 'integer')
  )]
  #[OA\Response(
    response: 200,
    description: 'Utilisateur supprimé avec succès'
  )]
  #[OA\Response(
    response: 404,
    description: 'Utilisateur non trouvé'
  )]
  public function delete(int $id): JsonResponse
  {
    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    try {
      $this->entityManager->remove($user);
      $this->entityManager->flush();

      return $this->json(['message' => 'Utilisateur supprimé avec succès']);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la suppression de l\'utilisateur',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/account/me', name: 'me', methods: ['GET'])]
  #[OA\Get(
    path: '/api/account/me',
    summary: 'Récupérer toutes les informations de l\'utilisateur connecté',
    description: 'Retourne le profil complet de l\'utilisateur actuellement connecté',
    tags: ['User']
  )]
  #[OA\Response(
    response: 200,
    description: 'Profil utilisateur récupéré avec succès'
  )]
  #[OA\Response(
    response: 401,
    description: 'Token invalide ou utilisateur non connecté'
  )]
  public function me(Request $request): JsonResponse
  {
    // Récupérer le token depuis les headers
    $authHeader = $request->headers->get('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }

    $token = substr($authHeader, 7); // Enlever "Bearer "

    try {
      // Décoder le token personnalisé (base64)
      $decodedToken = base64_decode($token);
      $parts = explode(':', $decodedToken);

      if (count($parts) < 3) {
        return $this->json(['error' => 'Format de token invalide'], Response::HTTP_UNAUTHORIZED);
      }

      $userId = (int)$parts[0];

      // Récupérer l'utilisateur depuis la table `users` (schéma existant)
      $row = $this->db->fetchAssociative('SELECT * FROM users WHERE id = :id', ['id' => $userId]);

      if (!$row) {
        return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
      }

      // Construire les rôles: base à partir de users.role puis compléter avec la table role/user_role
      $sqlRole = $row['role'] ?? 'user';
      $roles = ['ROLE_USER'];
      if ($sqlRole === 'admin') {
        $roles[] = 'ROLE_ADMIN';
      } elseif ($sqlRole === 'employee') {
        $roles[] = 'ROLE_EMPLOYED';
      }
      // Ajouter les rôles de la table role (libelle) via la table de jonction user_role
      $roleRows = $this->db->fetchAllAssociative(
        'SELECT r.libelle FROM role r INNER JOIN user_role ur ON ur.role_id = r.id WHERE ur.user_id = :uid',
        ['uid' => $userId]
      );
      foreach ($roleRows as $r) {
        if (!empty($r['libelle'])) {
          $roles[] = $r['libelle'];
        }
      }
      // Dédupliquer
      $roles = array_values(array_unique($roles));

      return $this->json([
        'id' => (int) $row['id'],
        'pseudo' => $row['pseudo'] ?? null,
        'email' => $row['email'] ?? null,
        // Champs non présents dans votre schéma sont omis
        'roles' => $roles,
        'credits' => isset($row['credits']) ? (int)$row['credits'] : null,
        'isSuspended' => isset($row['is_suspended']) ? (bool)$row['is_suspended'] : false,
        'createdAt' => $row['created_at'] ?? null,
      ]);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors du décodage du token',
        'details' => $e->getMessage()
      ], Response::HTTP_UNAUTHORIZED);
    }
  }

  #[Route('/account/role', name: 'account_role_set', methods: ['POST'])]
  public function setAccountRole(Request $request): JsonResponse
  {
    // Auth
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
    }
    $userId = (int) $parts[0];

    $data = json_decode($request->getContent(), true) ?? [];
    $driver = (bool) ($data['driver'] ?? false);
    $passenger = (bool) ($data['passenger'] ?? false);
    if (!$driver && !$passenger) {
      return $this->json(['error' => 'Sélection invalide: choisir chauffeur et/ou passager'], Response::HTTP_BAD_REQUEST);
    }

    try {
      // Assurer l'existence des rôles dans la table role
      $needed = [];
      if ($driver) $needed[] = 'ROLE_DRIVER';
      if ($passenger) $needed[] = 'ROLE_PASSENGER';

      foreach ($needed as $lib) {
        $exists = $this->db->fetchAssociative('SELECT id FROM role WHERE libelle = :lib', ['lib' => $lib]);
        $roleId = $exists['id'] ?? null;
        if (!$roleId) {
          $this->db->insert('role', ['libelle' => $lib]);
          $roleId = (int) $this->db->lastInsertId();
        }
        // Lier dans user_role si absent
        $link = $this->db->fetchAssociative('SELECT 1 FROM user_role WHERE user_id = :uid AND role_id = :rid', ['uid' => $userId, 'rid' => $roleId]);
        if (!$link) {
          $this->db->insert('user_role', ['user_id' => $userId, 'role_id' => $roleId]);
        }
      }

      // Nettoyer ceux non sélectionnés
      $toRemove = [];
      if (!$driver) $toRemove[] = 'ROLE_DRIVER';
      if (!$passenger) $toRemove[] = 'ROLE_PASSENGER';
      foreach ($toRemove as $lib) {
        $ridRow = $this->db->fetchAssociative('SELECT id FROM role WHERE libelle = :lib', ['lib' => $lib]);
        if ($ridRow && isset($ridRow['id'])) {
          $this->db->executeStatement('DELETE FROM user_role WHERE user_id = :uid AND role_id = :rid', ['uid' => $userId, 'rid' => (int)$ridRow['id']]);
        }
      }

      return $this->json(['status' => 'ok']);
    } catch (\Exception $e) {
      return $this->json(['error' => 'Erreur lors de la mise à jour des rôles', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/account/vehicles', name: 'account_vehicles_list', methods: ['GET'])]
  public function listVehicles(Request $request): JsonResponse
  {
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
    }
    $userId = (int) $parts[0];

    $rows = $this->db->fetchAllAssociative('SELECT id, user_id, brand, model, color, energy, plate_number, registration_date, seats FROM vehicles WHERE user_id = :uid ORDER BY id DESC', ['uid' => $userId]);
    return $this->json($rows);
  }

  #[Route('/account/vehicles', name: 'account_vehicles_add', methods: ['POST'])]
  public function addVehicle(Request $request): JsonResponse
  {
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
    }
    $userId = (int) $parts[0];

    $data = json_decode($request->getContent(), true) ?? [];
    foreach (['brand','model','color','energy','plateNumber','registrationDate','seats'] as $f) {
      if (!isset($data[$f]) || $data[$f] === '') {
        return $this->json(['error' => "Champ '$f' requis"], Response::HTTP_BAD_REQUEST);
      }
    }
    // energy limité à l'énum fourni
    $allowedEnergy = ['essence','diesel','electrique','hybride'];
    if (!in_array($data['energy'], $allowedEnergy, true)) {
      return $this->json(['error' => 'energy invalide'], Response::HTTP_BAD_REQUEST);
    }
    // seats
    $seats = (int) $data['seats'];
    if ($seats < 1 || $seats > 8) {
      return $this->json(['error' => 'seats doit être entre 1 et 8'], Response::HTTP_BAD_REQUEST);
    }
    // date
    $date = $data['registrationDate'];
    if (!\DateTime::createFromFormat('Y-m-d', $date)) {
      return $this->json(['error' => 'registrationDate format YYYY-MM-DD requis'], Response::HTTP_BAD_REQUEST);
    }

    try {
      $this->db->insert('vehicles', [
        'user_id' => $userId,
        'brand' => $data['brand'],
        'model' => $data['model'],
        'color' => $data['color'],
        'energy' => $data['energy'],
        'plate_number' => $data['plateNumber'],
        'registration_date' => $data['registrationDate'],
        'seats' => $seats,
      ]);
      return $this->json(['status' => 'ok']);
    } catch (\Exception $e) {
      return $this->json(['error' => 'Erreur lors de l\'ajout du véhicule', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/account/preferences/base', name: 'account_prefs_base_get', methods: ['GET'])]
  public function getBasePrefs(Request $request): JsonResponse
  {
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
    }
    $userId = (int) $parts[0];

    $rows = $this->db->fetchAllAssociative('SELECT preference FROM driver_preferences WHERE driver_id = :uid', ['uid' => $userId]);
    $prefs = ['smoker_allowed' => false, 'animals_allowed' => false];
    foreach ($rows as $r) {
      $pref = $r['preference'] ?? '';
      if (str_starts_with($pref, 'smoker_allowed:')) {
        $prefs['smoker_allowed'] = (substr($pref, strlen('smoker_allowed:')) === 'true');
      } elseif (str_starts_with($pref, 'animals_allowed:')) {
        $prefs['animals_allowed'] = (substr($pref, strlen('animals_allowed:')) === 'true');
      }
    }
    return $this->json($prefs);
  }

  #[Route('/account/preferences/base', name: 'account_prefs_base_set', methods: ['POST'])]
  public function setBasePrefs(Request $request): JsonResponse
  {
    $authHeader = $request->headers->get('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return $this->json(['error' => 'Token manquant'], Response::HTTP_UNAUTHORIZED);
    }
    $token = substr($authHeader, 7);
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
      return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
    }
    $userId = (int) $parts[0];

    $data = json_decode($request->getContent(), true) ?? [];
    $smoker = (bool) ($data['smoker_allowed'] ?? false);
    $animals = (bool) ($data['animals_allowed'] ?? false);

    try {
      // Upsert: supprimer existants et réinsérer
      $this->db->executeStatement('DELETE FROM driver_preferences WHERE driver_id = :uid AND (preference LIKE "smoker_allowed:%" OR preference LIKE "animals_allowed:%")', ['uid' => $userId]);
      $this->db->insert('driver_preferences', ['driver_id' => $userId, 'preference' => 'smoker_allowed:' . ($smoker ? 'true' : 'false')]);
      $this->db->insert('driver_preferences', ['driver_id' => $userId, 'preference' => 'animals_allowed:' . ($animals ? 'true' : 'false')]);
      return $this->json(['status' => 'ok']);
    } catch (\Exception $e) {
      return $this->json(['error' => 'Erreur lors de la mise à jour des préférences', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}
