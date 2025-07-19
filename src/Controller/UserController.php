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
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_users_')]
class UserController extends AbstractController
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UserRepository $userRepository,
    private ValidatorInterface $validator,
    private UserPasswordHasherInterface $passwordHasher
  ) {}

  #[Route('', name: 'list', methods: ['GET'])]
  #[OA\Get(
    path: '/api/users',
    summary: 'Récupérer tous les utilisateurs',
    description: 'Retourne la liste de tous les utilisateurs'
  )]
  #[OA\Parameter(
    name: 'role',
    in: 'query',
    description: 'Filtrer par rôle',
    required: false,
    schema: new OA\Schema(type: 'string', enum: ['ROLE_USER', 'ROLE_ADMIN'])
  )]
  #[OA\Parameter(
    name: 'search',
    in: 'query',
    description: 'Rechercher par nom, prénom ou email',
    required: false,
    schema: new OA\Schema(type: 'string')
  )]
  #[OA\Response(
    response: 200,
    description: 'Liste des utilisateurs récupérée avec succès',
    content: new OA\JsonContent(
      type: 'array',
      items: new OA\Items(
        type: 'object',
        properties: [
          new OA\Property(property: 'id', type: 'integer', example: 1),
          new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
          new OA\Property(property: 'firstName', type: 'string', example: 'John'),
          new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
          new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
          new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
        ]
      )
    )
  )]
  public function list(Request $request): JsonResponse
  {
    $role = $request->query->get('role');
    $search = $request->query->get('search');

    $queryBuilder = $this->userRepository->createQueryBuilder('u')
      ->orderBy('u.createdAt', 'DESC');

    if ($role) {
      $queryBuilder->andWhere('u.roles LIKE :role')
        ->setParameter('role', '%"' . $role . '"%');
    }

    if ($search) {
      $queryBuilder->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
        ->setParameter('search', '%' . $search . '%');
    }

    $users = $queryBuilder->getQuery()->getResult();

    $data = array_map(function (User $user) {
      return $this->formatUserData($user);
    }, $users);

    return $this->json($data);
  }

  #[Route('/{id}', name: 'show', methods: ['GET'])]
  #[OA\Get(
    path: '/api/users/{id}',
    summary: 'Récupérer un utilisateur par ID',
    description: 'Retourne les détails d\'un utilisateur spécifique'
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
    description: 'Utilisateur récupéré avec succès',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ridesAsDriver', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'bookings', type: 'array', items: new OA\Items(type: 'object'))
      ]
    )
  )]
  #[OA\Response(
    response: 404,
    description: 'Utilisateur non trouvé'
  )]
  public function show(int $id): JsonResponse
  {
    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    return $this->json($this->formatUserData($user, true));
  }

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
    $requiredFields = ['Email', 'Password'];
    foreach ($requiredFields as $field) {
      if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
      }
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

      // Générer un token simple (vous pourrez l'améliorer avec JWT plus tard)
      $token = base64_encode($user->getId() . ':' . time() . ':' . uniqid());

      $response = [
        'user' => $this->formatUserData($user),
        'message' => 'Connexion réussie',
        'token' => $token
      ];

      return $this->json($response, Response::HTTP_OK);
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

      $response = $this->formatUserData($user);
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

  #[Route('/{id}/complete-profile', name: 'complete_profile', methods: ['PATCH'])]
  #[OA\Patch(
    path: '/api/users/{id}/complete-profile',
    summary: 'Compléter le profil utilisateur',
    description: 'Permet à l\'utilisateur de compléter son profil après l\'inscription'
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
      properties: [
        new OA\Property(property: 'firstName', type: 'string', description: 'Prénom', example: 'John'),
        new OA\Property(property: 'lastName', type: 'string', description: 'Nom de famille', example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', description: 'Numéro de téléphone', example: '+33123456789'),
        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', description: 'Date de naissance', example: '1990-01-15'),
        new OA\Property(property: 'bio', type: 'string', description: 'Biographie', example: 'Passionné de covoiturage')
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Profil complété avec succès',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Profil complété avec succès'),
        new OA\Property(property: 'user', type: 'object')
      ]
    )
  )]
  #[OA\Response(
    response: 404,
    description: 'Utilisateur non trouvé'
  )]
  public function completeProfile(int $id, Request $request): JsonResponse
  {
    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true);

    if (!$data) {
      return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
    }

    try {
      // Mise à jour des informations de profil
      if (isset($data['firstName'])) {
        $user->setFirstName($data['firstName']);
      }
      if (isset($data['lastName'])) {
        $user->setLastName($data['lastName']);
      }
      if (isset($data['phone'])) {
        $user->setPhone($data['phone']);
      }
      if (isset($data['dateOfBirth'])) {
        $user->setBirthDate(new \DateTime($data['dateOfBirth']));
      }
      if (isset($data['bio'])) {
        $user->setBio($data['bio']);
      }

      // Valider l'entité
      $errors = $this->validator->validate($user);
      if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
          $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
      }

      $this->entityManager->flush();

      return $this->json([
        'message' => 'Profil complété avec succès',
        'user' => $this->formatUserData($user)
      ]);
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la mise à jour du profil',
        'details' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/{id}', name: 'update', methods: ['PUT'])]
  #[OA\Put(
    path: '/api/users/{id}',
    summary: 'Modifier un utilisateur',
    description: 'Met à jour les informations d\'un utilisateur'
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
      properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Adresse email'),
        new OA\Property(property: 'firstName', type: 'string', description: 'Prénom'),
        new OA\Property(property: 'lastName', type: 'string', description: 'Nom de famille'),
        new OA\Property(property: 'phone', type: 'string', description: 'Numéro de téléphone'),
        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', description: 'Date de naissance'),
        new OA\Property(property: 'bio', type: 'string', description: 'Biographie'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), description: 'Rôles de l\'utilisateur')
      ]
    )
  )]
  #[OA\Response(
    response: 200,
    description: 'Utilisateur modifié avec succès',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
      ]
    )
  )]
  #[OA\Response(
    response: 404,
    description: 'Utilisateur non trouvé'
  )]
  #[OA\Response(
    response: 409,
    description: 'Email déjà utilisé'
  )]
  public function update(int $id, Request $request): JsonResponse
  {
    $user = $this->userRepository->find($id);

    if (!$user) {
      return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true);

    if (!$data) {
      return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
    }

    try {
      // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
      if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
          return $this->json(['error' => 'Un utilisateur avec cette adresse email existe déjà'], Response::HTTP_CONFLICT);
        }
      }

      // Mise à jour des champs modifiables
      if (isset($data['email'])) {
        $user->setEmail($data['email']);
      }
      if (isset($data['firstName'])) {
        $user->setFirstName($data['firstName']);
      }
      if (isset($data['lastName'])) {
        $user->setLastName($data['lastName']);
      }
      if (isset($data['phone'])) {
        $user->setPhone($data['phone']);
      }
      if (isset($data['dateOfBirth'])) {
        $user->setBirthDate(new \DateTime($data['dateOfBirth']));
      }
      if (isset($data['bio'])) {
        $user->setBio($data['bio']);
      }
      if (isset($data['roles']) && is_array($data['roles'])) {
        $user->setRoles($data['roles']);
      }

      // Valider l'entité
      $errors = $this->validator->validate($user);
      if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
          $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
      }

      $this->entityManager->flush();

      return $this->json($this->formatUserData($user));
    } catch (\Exception $e) {
      return $this->json([
        'error' => 'Erreur lors de la modification de l\'utilisateur',
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

  private function formatUserData(User $user, bool $includeRelations = false): array
  {
    $data = [
      'id' => $user->getId(),
      'email' => $user->getEmail(),
      'pseudo' => $user->getPseudo(),
      'firstName' => $user->getFirstName(),
      'lastName' => $user->getLastName(),
      'phone' => $user->getPhone(),
      'dateOfBirth' => $user->getBirthDate()?->format('Y-m-d'),
      'bio' => $user->getBio(),
      'roles' => $user->getRoles(),
      'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
      'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
    ];

    if ($includeRelations) {
      // Trajets en tant que conducteur
      $data['ridesAsDriver'] = [];
      foreach ($user->getRidesAsDriver() as $ride) {
        $data['ridesAsDriver'][] = [
          'id' => $ride->getId(),
          'origin' => $ride->getOrigin(),
          'destination' => $ride->getDestination(),
          'departureTime' => $ride->getDepartureTime()?->format('Y-m-d H:i:s'),
          'status' => $ride->getStatus(),
          'availableSeats' => $ride->getAvailableSeats(),
          'price' => $ride->getPrice(),
        ];
      }

      // Réservations en tant que passager
      $data['bookings'] = [];
      foreach ($user->getBookings() as $booking) {
        $data['bookings'][] = [
          'id' => $booking->getId(),
          'numberOfSeats' => $booking->getNumberOfSeats(),
          'status' => $booking->getStatus(),
          'ride' => [
            'id' => $booking->getRide()?->getId(),
            'origin' => $booking->getRide()?->getOrigin(),
            'destination' => $booking->getRide()?->getDestination(),
            'departureTime' => $booking->getRide()?->getDepartureTime()?->format('Y-m-d H:i:s'),
          ],
        ];
      }
    }

    return $data;
  }
}
