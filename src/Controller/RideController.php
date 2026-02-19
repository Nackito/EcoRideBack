<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\Booking;
use App\Repository\RideRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/rides', name: 'api_rides_')]
class RideController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RideRepository $rideRepository,
        private UserRepository $userRepository,
        private ValidatorInterface $validator,
        private Connection $db
    ) {}

    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rides/search',
        summary: 'Rechercher des trajets',
        description: 'Recherche des trajets selon différents critères : lieu de départ, destination, date de départ et nombre de passagers',
        tags: ['Ride']
    )]
    #[OA\Parameter(
        name: 'departure',
        in: 'query',
        description: 'Lieu de départ (recherche partielle)',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'Paris')
    )]
    #[OA\Parameter(
        name: 'destination',
        in: 'query',
        description: 'Lieu d\'arrivée (recherche partielle)',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'Lyon')
    )]
    #[OA\Parameter(
        name: 'departureDate',
        in: 'query',
        description: 'Date de départ (format YYYY-MM-DD)',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date', example: '2024-12-25')
    )]
    #[OA\Parameter(
        name: 'passengers',
        in: 'query',
        description: 'Nombre de passagers souhaité (minimum de places disponibles)',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 8, example: 2)
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des trajets trouvés correspondant aux critères de recherche',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'X trajets trouvés'),
                new OA\Property(
                    property: 'rides',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                            new OA\Property(property: 'destination', type: 'string', example: 'Lyon'),
                            new OA\Property(property: 'departureDate', type: 'string', format: 'date', example: '2024-12-25'),
                            new OA\Property(property: 'departureHour', type: 'string', format: 'time', example: '14:30:00'),
                            new OA\Property(property: 'availableSeats', type: 'integer', example: 3),
                            new OA\Property(property: 'remainingSeats', type: 'integer', example: 2),
                            new OA\Property(property: 'price', type: 'string', example: '25.50'),
                            new OA\Property(property: 'driver', type: 'object'),
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Paramètres de recherche invalides'
    )]
    public function search(Request $request): JsonResponse
    {
        $departure = $request->query->get('departure');
        $destination = $request->query->get('destination');
        $departureDate = $request->query->get('departureDate');
        $passengers = $request->query->get('passengers');

        // Validation des paramètres
        if ($passengers !== null) {
            $passengers = (int) $passengers;
            if ($passengers < 1 || $passengers > 8) {
                return $this->json([
                    'success' => false,
                    'error' => 'Le nombre de passagers doit être entre 1 et 8'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($departureDate !== null) {
            try {
                $dateObj = new \DateTime($departureDate);
                // Vérifier que la date n'est pas dans le passé
                $today = new \DateTime('today');
                if ($dateObj < $today) {
                    return $this->json([
                        'success' => false,
                        'error' => 'La date de départ ne peut pas être dans le passé'
                    ], Response::HTTP_BAD_REQUEST);
                }
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Format de date invalide. Utilisez le format YYYY-MM-DD'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Construction de la requête sur la table SQL réelle `trips`
        $sql = 'SELECT id, driver_id, vehicle_id, departure_city, arrival_city, departure_datetime, arrival_datetime, price, eco, seats_left, status
                FROM trips
                WHERE status IN ("active", "planned")';
        $params = [];

        if (!empty($departure)) {
            $sql .= ' AND LOWER(departure_city) LIKE LOWER(:departure)';
            $params['departure'] = '%' . $departure . '%';
        }

        if (!empty($destination)) {
            $sql .= ' AND LOWER(arrival_city) LIKE LOWER(:destination)';
            $params['destination'] = '%' . $destination . '%';
        }

        if ($departureDate) {
            $sql .= ' AND DATE(departure_datetime) = :departureDate';
            $params['departureDate'] = $departureDate;
        }

        if ($passengers) {
            $sql .= ' AND seats_left >= :passengers';
            $params['passengers'] = (int) $passengers;
        }

        $sql .= ' ORDER BY departure_datetime ASC';

        $rows = $this->db->fetchAllAssociative($sql, $params);

        // Formater les résultats au format attendu par le front
        $ridesData = array_map(function (array $row) {
            return $this->formatTripRow($row);
        }, $rows);

        return $this->json([
            'success' => true,
            'message' => count($rows) . ' trajet' . (count($rows) > 1 ? 's' : '') . ' trouvé' . (count($rows) > 1 ? 's' : ''),
            'rides' => $ridesData,
            'total' => count($rows)
        ]);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rides',
        summary: 'Lister tous les trajets actifs',
        description: 'Retourne la liste de tous les trajets actifs',
        tags: ['Ride']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des trajets actifs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'rides',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                            new OA\Property(property: 'destination', type: 'string', example: 'Lyon'),
                            new OA\Property(property: 'departureDate', type: 'string', format: 'date', example: '2024-12-25'),
                            new OA\Property(property: 'departureHour', type: 'string', format: 'time', example: '14:30:00'),
                            new OA\Property(property: 'availableSeats', type: 'integer', example: 3),
                            new OA\Property(property: 'remainingSeats', type: 'integer', example: 2),
                            new OA\Property(property: 'price', type: 'string', example: '25.50'),
                            new OA\Property(property: 'status', type: 'string', example: 'active'),
                            new OA\Property(property: 'driver', type: 'object'),
                        ]
                    )
                ),
                new OA\Property(property: 'total', type: 'integer', example: 5)
            ]
        )
    )]
    public function list(): JsonResponse
    {
        // Lire depuis la table SQL personnalisée 'trips'
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, driver_id, vehicle_id, departure_city, arrival_city, departure_datetime, arrival_datetime, price, eco, seats_left, status
             FROM trips
             WHERE status IN ("active", "planned")
             ORDER BY departure_datetime ASC'
        );

        $data = array_map(function (array $row) {
            return $this->formatTripRow($row);
        }, $rows);

        return $this->json([
            'success' => true,
            'rides' => $data,
            'total' => count($rows)
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rides/{id}',
        summary: 'Récupérer un trajet par ID',
        description: 'Retourne les détails d\'un trajet spécifique',
        tags: ['Ride']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du trajet',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Trajet récupéré avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                new OA\Property(property: 'destination', type: 'string', example: 'Lyon'),
                new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', example: '2023-12-25T14:30:00'),
                new OA\Property(property: 'availableSeats', type: 'integer', example: 3),
                new OA\Property(property: 'remainingSeats', type: 'integer', example: 2),
                new OA\Property(property: 'price', type: 'string', example: '25.50'),
                new OA\Property(property: 'description', type: 'string', example: 'Trajet direct, non-fumeur'),
                new OA\Property(property: 'status', type: 'string', example: 'active')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Trajet non trouvé'
    )]
    public function show(int $id): JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, driver_id, vehicle_id, departure_city, arrival_city, departure_datetime, arrival_datetime, price, eco, seats_left, status
             FROM trips WHERE id = :id',
            ['id' => $id]
        );

        if (!$row) {
            return $this->json(['error' => 'Trajet non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatTripRow($row));
    }

    #[Route('/{id}/book', name: 'book', methods: ['POST'])]
    public function book(int $id, Request $request): JsonResponse
    {
        // Auth basique via token Bearer
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
        $passengerId = (int) $parts[0];

        try {
            $trip = $this->db->fetchAssociative(
                'SELECT id, driver_id, departure_datetime, seats_left, status FROM trips WHERE id = :id',
                ['id' => $id]
            );

            if (!$trip) {
                return $this->json(['error' => 'Trajet introuvable'], Response::HTTP_NOT_FOUND);
            }

            // Empêcher l'auto-réservation
            if ((int)$trip['driver_id'] === $passengerId) {
                return $this->json(['error' => 'Vous ne pouvez pas réserver votre propre trajet'], Response::HTTP_BAD_REQUEST);
            }

            // Statut autorisé
            $status = (string)($trip['status'] ?? '');
            if (!in_array($status, ['active', 'planned'], true)) {
                return $this->json(['error' => 'Ce trajet n\'est pas réservable'], Response::HTTP_BAD_REQUEST);
            }

            // Date future
            $dep = new \DateTime((string)$trip['departure_datetime']);
            if ($dep <= new \DateTime()) {
                return $this->json(['error' => 'Ce trajet est déjà parti'], Response::HTTP_BAD_REQUEST);
            }

            $seatsLeft = (int)($trip['seats_left'] ?? 0);
            if ($seatsLeft <= 0) {
                return $this->json(['error' => 'Plus de places disponibles'], Response::HTTP_CONFLICT);
            }

            // Réserver 1 place: décrément atomique
            $affected = $this->db->executeStatement(
                'UPDATE trips SET seats_left = seats_left - 1 WHERE id = :id AND seats_left > 0',
                ['id' => $id]
            );

            if ($affected < 1) {
                return $this->json(['error' => 'Réservation impossible, plus de places'], Response::HTTP_CONFLICT);
            }

            $updated = $this->db->fetchAssociative('SELECT seats_left FROM trips WHERE id = :id', ['id' => $id]);

            return $this->json([
                'success' => true,
                'message' => 'Demande de réservation envoyée',
                'rideId' => $id,
                'seatsLeft' => isset($updated['seats_left']) ? (int)$updated['seats_left'] : null,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la réservation',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/rides',
        summary: 'Créer un nouveau trajet',
        description: 'Ajoute un nouveau trajet à la base de données',
        tags: ['Ride']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['departure', 'destination', 'departureDate', 'departureHour', 'availableSeats', 'price'],
            properties: [
                new OA\Property(property: 'departure', type: 'string', description: 'Lieu de départ', example: 'Paris'),
                new OA\Property(property: 'destination', type: 'string', description: 'Lieu d\'arrivée', example: 'Lyon'),
                new OA\Property(property: 'departureDate', type: 'string', format: 'date', description: 'Date de départ', example: '2023-12-25'),
                new OA\Property(property: 'departureHour', type: 'string', format: 'time', description: 'Heure de départ', example: '14:30'),
                new OA\Property(property: 'availableSeats', type: 'integer', description: 'Nombre de places disponibles', example: 3),
                new OA\Property(property: 'price', type: 'number', format: 'float', description: 'Prix par personne', example: 25.50),
                new OA\Property(property: 'description', type: 'string', description: 'Description du trajet', example: 'Trajet direct, non-fumeur'),
                new OA\Property(property: 'status', type: 'string', description: 'Statut du trajet', example: 'active'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Trajet créé avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                new OA\Property(property: 'destination', type: 'string', example: 'Lyon'),
                new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', example: '2023-12-25T14:30:00'),
                new OA\Property(property: 'availableSeats', type: 'integer', example: 3),
                new OA\Property(property: 'remainingSeats', type: 'integer', example: 2),
                new OA\Property(property: 'price', type: 'string', example: '25.50'),
                new OA\Property(property: 'description', type: 'string', example: 'Trajet direct, non-fumeur'),
                new OA\Property(property: 'status', type: 'string', example: 'active'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-01-01T10:00:00'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2023-01-01T10:00:00')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    public function create(Request $request): JsonResponse
    {
        // Auth: exiger un token Bearer et lier le conducteur à l'utilisateur connecté
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
        $driverId = (int) $parts[0];

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
        $requiredFields = ['departure', 'destination', 'departureDate', 'departureHour', 'availableSeats', 'price'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Récupérer le conducteur depuis le token d'authentification
        // Obtenir une référence sans requête SQL directe (évite table manquante)
        $driver = $this->entityManager->getReference(\App\Entity\User::class, $driverId);

        try {
            // Construire les datetime à partir de date + heure
            $depDate = (string) $data['departureDate'];
            $depHour = (string) $data['departureHour'];
            $departureDT = new \DateTime(trim($depDate . ' ' . $depHour));

            $arrivalDT = null;
            if (!empty($data['arrivalDate']) || !empty($data['arrivalHour'])) {
                $arrDate = (string) ($data['arrivalDate'] ?? $depDate);
                $arrHour = (string) ($data['arrivalHour'] ?? '');
                $arrivalDT = new \DateTime(trim($arrDate . ' ' . $arrHour));
            }
            // Fallback: si non fourni, définir une heure d'arrivée par défaut ( +2h )
            if ($arrivalDT === null) {
                $arrivalDT = (clone $departureDT)->modify('+2 hours');
            }

            // Récupérer un véhicule pour l'utilisateur (requis par la contrainte DB)
            $vehRow = $this->db->fetchAssociative('SELECT id FROM vehicles WHERE user_id = :uid ORDER BY id DESC LIMIT 1', ['uid' => $driverId]);
            $vehicleId = $vehRow['id'] ?? null;
            if ($vehicleId === null) {
                return $this->json([
                    'error' => 'Aucun véhicule enregistré',
                    'details' => 'Vous devez enregistrer un véhicule avant de publier un trajet',
                    'redirect' => '/vehiclemanagement'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Normaliser le prix (laisser le statut géré par la BDD)
            $price = number_format((float) $data['price'], 2, '.', '');

            // Insérer dans la table SQL personnalisée 'trips'
            $this->db->insert('trips', [
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'departure_city' => (string) $data['departure'],
                'arrival_city' => (string) $data['destination'],
                'departure_datetime' => $departureDT->format('Y-m-d H:i:s'),
                'arrival_datetime' => $arrivalDT->format('Y-m-d H:i:s'),
                'price' => $price,
                'eco' => 0,
                'seats_left' => (int) $data['availableSeats'],
                // status: laisser la colonne utiliser sa valeur par défaut
            ]);

            $newId = (int) $this->db->lastInsertId();
            // Lire le statut réellement stocké
            $row = $this->db->fetchAssociative('SELECT status FROM trips WHERE id = :id', ['id' => $newId]);
            $storedStatus = $row['status'] ?? 'planned';

            return $this->json([
                'success' => true,
                'trip' => [
                    'id' => $newId,
                    'driverId' => $driverId,
                    'vehicleId' => $vehicleId,
                    'departureCity' => (string) $data['departure'],
                    'arrivalCity' => (string) $data['destination'],
                    'departureDatetime' => $departureDT->format('Y-m-d H:i:s'),
                    'arrivalDatetime' => $arrivalDT->format('Y-m-d H:i:s'),
                    'price' => $price,
                    'eco' => false,
                    'seatsLeft' => (int) $data['availableSeats'],
                    'status' => $storedStatus,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création du trajet',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/rides/{id}',
        summary: 'Modifier un trajet',
        description: 'Met à jour les informations d\'un trajet',
        tags: ['Ride']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du trajet',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'origin', type: 'string', description: 'Lieu de départ'),
                new OA\Property(property: 'destination', type: 'string', description: 'Lieu d\'arrivée'),
                new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', description: 'Heure de départ'),
                new OA\Property(property: 'availableSeats', type: 'integer', description: 'Nombre de places disponibles'),
                new OA\Property(property: 'price', type: 'number', format: 'float', description: 'Prix par personne'),
                new OA\Property(property: 'description', type: 'string', description: 'Description du trajet'),
                new OA\Property(property: 'conditions', type: 'string', description: 'Conditions spéciales'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Trajet modifié avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                new OA\Property(property: 'destination', type: 'string', example: 'Lyon'),
                new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', example: '2023-12-25T14:30:00'),
                new OA\Property(property: 'availableSeats', type: 'integer', example: 3),
                new OA\Property(property: 'price', type: 'string', example: '25.50'),
                new OA\Property(property: 'description', type: 'string', example: 'Trajet direct, non-fumeur'),
                new OA\Property(property: 'status', type: 'string', example: 'active')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Trajet non trouvé'
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $ride = $this->rideRepository->find($id);

        if (!$ride) {
            return $this->json(['error' => 'Trajet non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le trajet peut encore être modifié
        if ($ride->getStatus() !== 'active') {
            return $this->json(['error' => 'Ce trajet ne peut plus être modifié'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Mise à jour des champs modifiables
            if (isset($data['origin'])) {
                $ride->setOrigin($data['origin']);
            }
            if (isset($data['destination'])) {
                $ride->setDestination($data['destination']);
            }
            if (isset($data['departureTime'])) {
                $ride->setDepartureTime(new \DateTime($data['departureTime']));
            }
            if (isset($data['availableSeats'])) {
                $ride->setAvailableSeats((int)$data['availableSeats']);
            }
            if (isset($data['price'])) {
                $ride->setPrice((string)$data['price']);
            }
            if (isset($data['description'])) {
                $ride->setDescription($data['description']);
            }
            if (isset($data['conditions'])) {
                $ride->setConditions($data['conditions']);
            }

            // Valider l'entité
            $errors = $this->validator->validate($ride);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            return $this->json($this->formatRideData($ride));
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la modification du trajet',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/start', name: 'start', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/rides/{id}/start',
        summary: 'Démarrer un trajet',
        description: 'Marque un trajet comme démarré et change son statut',
        tags: ['Ride']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du trajet',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Trajet démarré avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Trajet démarré avec succès'),
                new OA\Property(
                    property: 'ride',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                        new OA\Property(property: 'origin', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'destination', type: 'string', example: 'Lyon')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Trajet non trouvé'
    )]
    #[OA\Response(
        response: 400,
        description: 'Le trajet ne peut pas être démarré'
    )]
    public function start(int $id): JsonResponse
    {
        $ride = $this->rideRepository->find($id);

        if (!$ride) {
            return $this->json(['error' => 'Trajet non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le trajet peut être démarré
        if ($ride->getStatus() !== 'active') {
            return $this->json(['error' => 'Ce trajet ne peut pas être démarré'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que l'heure de départ est proche
        $now = new \DateTime();
        $departureTime = $ride->getDepartureTime();
        $timeDiff = $now->getTimestamp() - $departureTime->getTimestamp();

        // Permettre de démarrer 30 minutes avant l'heure prévue jusqu'à 2 heures après
        if ($timeDiff < -1800 || $timeDiff > 7200) {
            return $this->json([
                'error' => 'Le trajet ne peut être démarré que 30 minutes avant l\'heure prévue et jusqu\'à 2 heures après',
                'current_time' => $now->format('Y-m-d H:i:s'),
                'departure_time' => $departureTime->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ride->setStatus('completed');
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Trajet démarré avec succès',
                'ride' => $this->formatRideData($ride)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors du démarrage du trajet',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/rides/{id}/cancel',
        summary: 'Annuler un trajet',
        description: 'Annule un trajet et change son statut',
        tags: ['Ride']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du trajet',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Trajet annulé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Trajet non trouvé'
    )]
    public function cancel(int $id): JsonResponse
    {
        $ride = $this->rideRepository->find($id);

        if (!$ride) {
            return $this->json(['error' => 'Trajet non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($ride->getStatus() !== 'active') {
            return $this->json(['error' => 'Ce trajet ne peut pas être annulé'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ride->setStatus('cancelled');
            $this->entityManager->flush();

            return $this->json(['message' => 'Trajet annulé avec succès']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'annulation du trajet',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function formatRideData(Ride $ride, bool $includeBookings = false): array
    {
        $data = [
            'id' => $ride->getId(),
            'origin' => $ride->getOrigin(),
            'destination' => $ride->getDestination(),
            'departureDate' => $ride->getDepartureDate()?->format('Y-m-d'),
            'departureHour' => $ride->getDepartureHour()?->format('H:i:s'),
            'arrivalDate' => $ride->getArrivalDate()?->format('Y-m-d'),
            'arrivalHour' => $ride->getArrivalHour()?->format('H:i:s'),
            'availableSeats' => $ride->getAvailableSeats(),
            'remainingSeats' => $ride->getRemainingSeats(),
            'price' => $ride->getPrice(),
            'description' => $ride->getDescription(),
            'status' => $ride->getStatus(),
            'createdAt' => $ride->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $ride->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'driver' => (function () use ($ride) {
                $driverId = $ride->getDriver()?->getId();
                $driverData = ['id' => $driverId, 'email' => null, 'firstName' => null, 'lastName' => null];
                if ($driverId) {
                    try {
                        $row = $this->db->fetchAssociative('SELECT email, pseudo FROM users WHERE id = :id', ['id' => $driverId]);
                        if ($row) {
                            $driverData['email'] = $row['email'] ?? null;
                            // Conserver les clés attendues; firstName/lastName non disponibles dans ce schéma
                        }
                    } catch (\Throwable $e) {
                        // En cas d'erreur DBAL, retourner au moins l'id
                    }
                }
                return $driverData;
            })(),
            'canBeBooked' => $ride->canBeBooked(),
            'isActive' => $ride->isActive(),
        ];

        if ($includeBookings) {
            $data['bookings'] = [];
            foreach ($ride->getBookings() as $booking) {
                $data['bookings'][] = [
                    'id' => $booking->getId(),
                    'numberOfSeats' => $booking->getNumberOfSeats(),
                    'status' => $booking->getStatus(),
                    'passenger' => [
                        'id' => $booking->getPassenger()?->getId(),
                        'email' => $booking->getPassenger()?->getEmail(),
                        'firstName' => $booking->getPassenger()?->getFirstName(),
                        'lastName' => $booking->getPassenger()?->getLastName(),
                    ],
                ];
            }
        }

        return $data;
    }

    private function formatTripRow(array $row): array
    {
        // Split datetime into date and hour strings
        $depDT = $row['departure_datetime'] ?? null;
        $arrDT = $row['arrival_datetime'] ?? null;
        $depDate = $depDT ? (new \DateTime($depDT))->format('Y-m-d') : null;
        $depHour = $depDT ? (new \DateTime($depDT))->format('H:i:s') : null;
        $arrDate = $arrDT ? (new \DateTime($arrDT))->format('Y-m-d') : null;
        $arrHour = $arrDT ? (new \DateTime($arrDT))->format('H:i:s') : null;

        // Driver minimal info via SQL users
        $driverId = isset($row['driver_id']) ? (int)$row['driver_id'] : null;
        $driver = ['id' => $driverId, 'email' => null, 'name' => null];
        if ($driverId) {
            try {
                $u = $this->db->fetchAssociative('SELECT email, pseudo FROM users WHERE id = :id', ['id' => $driverId]);
                if ($u) {
                    $driver['email'] = $u['email'] ?? null;
                    $driver['name'] = $u['pseudo'] ?? null;
                }
            } catch (\Throwable $e) {
            }
        }

        // Vehicle type via vehicles.energy
        $vehicleType = null;
        if (!empty($row['vehicle_id'])) {
            try {
                $v = $this->db->fetchAssociative('SELECT energy FROM vehicles WHERE id = :id', ['id' => (int)$row['vehicle_id']]);
                $energy = $v['energy'] ?? null;
                if ($energy === 'electrique') $vehicleType = 'electric';
                elseif ($energy === 'hybride') $vehicleType = 'hybrid';
                else $vehicleType = 'thermal';
            } catch (\Throwable $e) {
            }
        }

        return [
            'id' => (int)$row['id'],
            'origin' => $row['departure_city'] ?? null,
            'destination' => $row['arrival_city'] ?? null,
            'departureDate' => $depDate,
            'departureHour' => $depHour,
            'arrivalDate' => $arrDate,
            'arrivalHour' => $arrHour,
            'availableSeats' => isset($row['seats_left']) ? (int)$row['seats_left'] : 0,
            'remainingSeats' => isset($row['seats_left']) ? (int)$row['seats_left'] : 0,
            'price' => isset($row['price']) ? (string)$row['price'] : '0',
            'description' => null,
            'status' => $row['status'] ?? 'planned',
            'driver' => $driver,
            'vehicleType' => $vehicleType,
            'createdAt' => null,
            'updatedAt' => null,
            'canBeBooked' => true,
            'isActive' => ($row['status'] ?? '') === 'active',
        ];
    }
}
