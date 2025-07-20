<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
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
    private UserPasswordHasherInterface $passwordHasher
  ) {}

  #[Route('/login', name: 'login', methods: ['POST'])]
  #[OA\Post(
    path: '/api/login',
    summary: 'Connexion utilisateur',
    description: 'Authentifie un utilisateur avec son email/pseudo et mot de passe'
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

      // Rechercher l'utilisateur par email ou pseudo
      $user = null;

      // Vérifier si c'est un email
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = $this->userRepository->findOneBy(['email' => $email]);
      } else {
        $user = $this->userRepository->findOneBy(['pseudo' => $email]);
      }

      if (!$user) {
        return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_UNAUTHORIZED);
      }

      // Vérifier le mot de passe
      if (!$this->passwordHasher->isPasswordValid($user, $password)) {
        return $this->json(['error' => 'Mot de passe incorrect'], Response::HTTP_UNAUTHORIZED);
      }

      // Générer un token simple
      $token = base64_encode($user->getId() . ':' . time() . ':' . uniqid());

      return $this->json([
        'user' => [
          'id' => $user->getId(),
          'pseudo' => $user->getPseudo(),
          'email' => $user->getEmail(),
          'firstName' => $user->getFirstName(),
          'lastName' => $user->getLastName(),
          'roles' => $user->getRoles(),
          'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
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
    description: 'Ajoute un nouvel utilisateur à la base de données'
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

    // Vérifier que l'email n'existe pas déjà
    $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
    if ($existingUser) {
      return $this->json(['error' => 'Un utilisateur avec cette adresse email existe déjà'], Response::HTTP_CONFLICT);
    }

    // Vérifier que le pseudo n'existe pas déjà
    $existingUserByPseudo = $this->userRepository->findOneBy(['pseudo' => $data['pseudo']]);
    if ($existingUserByPseudo) {
      return $this->json(['error' => 'Un utilisateur avec ce pseudo existe déjà'], Response::HTTP_CONFLICT);
    }

    try {
      $user = new User();
      $user->setEmail($data['email']);
      $user->setPseudo($data['pseudo']);
      $user->setFirstName($data['pseudo']); // Utiliser le pseudo comme firstName temporairement
      $user->setLastName('À compléter'); // Valeur temporaire pour respecter les contraintes

      // Hasher le mot de passe
      $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
      $user->setPassword($hashedPassword);

      // Définir les rôles
      $userRoles = ['ROLE_USER']; // Rôle de base obligatoire

      if (isset($data['roles']) && is_array($data['roles'])) {
        // Valider et ajouter les rôles autorisés
        $allowedRoles = ['ROLE_DRIVER', 'ROLE_PASSENGER', 'ROLE_ADMIN'];
        foreach ($data['roles'] as $role) {
          if (in_array($role, $allowedRoles) && !in_array($role, $userRoles)) {
            $userRoles[] = $role;
          }
        }
      }

      $user->setRoles($userRoles);

      // Valider l'entité
      $errors = $this->validator->validate($user);
      if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
          $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
      }

      $this->entityManager->persist($user);
      $this->entityManager->flush();

      $response = $user->toArray();
      $response['pseudo'] = $data['pseudo'];
      $response['message'] = 'Inscription réussie. Vous pouvez maintenant compléter votre profil.';

      return $this->json($response, Response::HTTP_CREATED);
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
    description: 'Met à jour le mot de passe d\'un utilisateur'
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
    description: 'Supprime un utilisateur de la base de données'
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
    description: 'Retourne le profil complet de l\'utilisateur actuellement connecté'
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

      // Récupérer l'utilisateur par ID
      $user = $this->userRepository->find($userId);

      if (!$user) {
        return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
      }

      // Retourner les informations utilisateur complètes
      return $this->json([
        'id' => $user->getId(),
        'pseudo' => $user->getPseudo(),
        'email' => $user->getEmail(),
        'firstName' => $user->getFirstName(),
        'lastName' => $user->getLastName(),
        'phone' => $user->getPhone(),
        'bio' => $user->getBio(),
        'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
        'roles' => $user->getRoles(),
        'isVerified' => $user->isVerified(),
        'rating' => $user->getRating(),
        'totalRides' => $user->getTotalRides(),
        'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
      ]);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors du décodage du token',
        'details' => $e->getMessage()
      ], Response::HTTP_UNAUTHORIZED);
    }
  }
}
