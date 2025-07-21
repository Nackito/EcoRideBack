<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\Booking;
use App\Repository\RideRepository;
use App\Repository\UserRepository;
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
        private ValidatorInterface $validator
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

        // Construction de la requête
        $queryBuilder = $this->rideRepository->createQueryBuilder('r')
            ->leftJoin('r.driver', 'd')
            ->addSelect('d')
            ->where('r.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('r.departureDate', 'ASC')
            ->addOrderBy('r.departureHour', 'ASC');

        // Filtres de recherche
        if (!empty($departure)) {
            $queryBuilder->andWhere('LOWER(r.origin) LIKE LOWER(:departure)')
                ->setParameter('departure', '%' . $departure . '%');
        }

        if (!empty($destination)) {
            $queryBuilder->andWhere('LOWER(r.destination) LIKE LOWER(:destination)')
                ->setParameter('destination', '%' . $destination . '%');
        }

        if ($departureDate) {
            $queryBuilder->andWhere('r.departureDate = :departureDate')
                ->setParameter('departureDate', new \DateTime($departureDate));
        }

        if ($passengers) {
            $queryBuilder->andWhere('r.availableSeats >= :passengers')
                ->setParameter('passengers', $passengers);
        }

        $rides = $queryBuilder->getQuery()->getResult();

        // Formater les résultats
        $ridesData = array_map(function (Ride $ride) {
            return $this->formatRideData($ride);
        }, $rides);

        return $this->json([
            'success' => true,
            'message' => count($rides) . ' trajet' . (count($rides) > 1 ? 's' : '') . ' trouvé' . (count($rides) > 1 ? 's' : ''),
            'rides' => $ridesData,
            'total' => count($rides)
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
        $rides = $this->rideRepository->findBy(
            ['status' => 'active'],
            ['departureDate' => 'ASC', 'departureHour' => 'ASC']
        );

        $data = array_map(function (Ride $ride) {
            return $this->formatRideData($ride);
        }, $rides);

        return $this->json([
            'success' => true,
            'rides' => $data,
            'total' => count($rides)
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
        $ride = $this->rideRepository->find($id);

        if (!$ride) {
            return $this->json(['error' => 'Trajet non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatRideData($ride, true));
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

        // TODO: Récupérer le conducteur depuis le token d'authentification
        // Pour l'instant, utiliser un conducteur par défaut (ID = 1)
        // Dans une vraie application, il faut décoder le JWT et récupérer l'ID utilisateur
        $driver = $this->userRepository->find(1);
        if (!$driver) {
            return $this->json(['error' => 'Conducteur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $ride = new Ride();
            $ride->setOrigin($data['departure']);
            $ride->setDestination($data['destination']);
            $ride->setDepartureDate(new \DateTime($data['departureDate']));
            $ride->setDepartureHour(new \DateTime($data['departureHour']));
            $ride->setAvailableSeats((int)$data['availableSeats']);
            $ride->setPrice((string)$data['price']);
            $ride->setDriver($driver);

            // Champs optionnels
            if (isset($data['arrivalDate']) && !empty($data['arrivalDate'])) {
                $ride->setArrivalDate(new \DateTime($data['arrivalDate']));
            }
            if (isset($data['arrivalHour']) && !empty($data['arrivalHour'])) {
                $ride->setArrivalHour(new \DateTime($data['arrivalHour']));
            }
            if (isset($data['description'])) {
                $ride->setDescription($data['description']);
            }

            // Statut par défaut
            $status = isset($data['status']) ? $data['status'] : 'active';
            $ride->setStatus($status);

            // Valider l'entité
            $errors = $this->validator->validate($ride);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($ride);
            $this->entityManager->flush();

            return $this->json($this->formatRideData($ride), Response::HTTP_CREATED);
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
            'driver' => [
                'id' => $ride->getDriver()?->getId(),
                'email' => $ride->getDriver()?->getEmail(),
                'firstName' => $ride->getDriver()?->getFirstName(),
                'lastName' => $ride->getDriver()?->getLastName(),
            ],
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
}
