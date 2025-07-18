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

    #[Route('', name: 'list', methods: ['GET'])]
    /*#[OA\Get(
        path: '/api/rides',
        summary: 'Rechercher des trajets',
        description: 'Recherche des trajets selon différents critères'
    )]
    #[OA\Parameter(
        name: 'origin',
        in: 'query',
        description: 'Lieu de départ',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'destination',
        in: 'query',
        description: 'Lieu d\'arrivée',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'date',
        in: 'query',
        description: 'Date de départ (YYYY-MM-DD)',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'seats',
        in: 'query',
        description: 'Nombre de places minimum requises',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1)
    )]
    #[OA\Parameter(
        name: 'status',
        in: 'query',
        description: 'Statut du trajet',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['active', 'completed', 'cancelled'])
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des trajets trouvés',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Ride')
        )
    )]*/
    public function search(Request $request): JsonResponse
    {
        $origin = $request->query->get('origin');
        $destination = $request->query->get('destination');
        $date = $request->query->get('date');
        $seats = $request->query->get('seats');
        $status = $request->query->get('status', 'active');

        $queryBuilder = $this->rideRepository->createQueryBuilder('r')
            ->leftJoin('r.driver', 'd')
            ->addSelect('d')
            ->where('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.departureTime', 'ASC');

        if ($origin) {
            $queryBuilder->andWhere('r.origin LIKE :origin')
                ->setParameter('origin', '%' . $origin . '%');
        }

        if ($destination) {
            $queryBuilder->andWhere('r.destination LIKE :destination')
                ->setParameter('destination', '%' . $destination . '%');
        }

        if ($date) {
            $startDate = new \DateTime($date . ' 00:00:00');
            $endDate = new \DateTime($date . ' 23:59:59');
            $queryBuilder->andWhere('r.departureTime BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        if ($seats) {
            $queryBuilder->andWhere('r.availableSeats >= :seats')
                ->setParameter('seats', $seats);
        }

        $rides = $queryBuilder->getQuery()->getResult();

        $data = array_map(function (Ride $ride) {
            return $this->formatRideData($ride);
        }, $rides);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rides/{id}',
        summary: 'Récupérer un trajet par ID',
        description: 'Retourne les détails d\'un trajet spécifique'
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
        description: 'Ajoute un nouveau trajet à la base de données'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['origin', 'destination', 'departureTime', 'availableSeats', 'price', 'driverId'],
            properties: [
                new OA\Property(property: 'origin', type: 'string', description: 'Lieu de départ', example: 'Paris'),
                new OA\Property(property: 'destination', type: 'string', description: 'Lieu d\'arrivée', example: 'Lyon'),
                new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', description: 'Heure de départ', example: '2023-12-25T14:30:00'),
                new OA\Property(property: 'availableSeats', type: 'integer', description: 'Nombre de places disponibles', example: 3),
                new OA\Property(property: 'price', type: 'number', format: 'float', description: 'Prix par personne', example: 25.50),
                new OA\Property(property: 'driverId', type: 'integer', description: 'ID du conducteur', example: 1),
                new OA\Property(property: 'description', type: 'string', description: 'Description du trajet', example: 'Trajet direct, non-fumeur'),
                new OA\Property(property: 'originLatLng', type: 'string', description: 'Coordonnées GPS du départ', example: '48.8566,2.3522'),
                new OA\Property(property: 'destinationLatLng', type: 'string', description: 'Coordonnées GPS de l\'arrivée', example: '45.7640,4.8357'),
                new OA\Property(property: 'estimatedDistance', type: 'integer', description: 'Distance estimée en km', example: 465),
                new OA\Property(property: 'estimatedDuration', type: 'integer', description: 'Durée estimée en heures', example: 4),
                new OA\Property(property: 'waypoints', type: 'array', items: new OA\Items(type: 'string'), description: 'Points d\'arrêt intermédiaires'),
                new OA\Property(property: 'conditions', type: 'string', description: 'Conditions spéciales', example: 'Non-fumeur, pas d\'animaux'),
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
        $requiredFields = ['origin', 'destination', 'departureTime', 'availableSeats', 'price', 'driverId'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier que le conducteur existe
        $driver = $this->userRepository->find($data['driverId']);
        if (!$driver) {
            return $this->json(['error' => 'Conducteur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $ride = new Ride();
            $ride->setOrigin($data['origin']);
            $ride->setDestination($data['destination']);
            $ride->setDepartureTime(new \DateTime($data['departureTime']));
            $ride->setAvailableSeats((int)$data['availableSeats']);
            $ride->setPrice((string)$data['price']);
            $ride->setDriver($driver);

            // Champs optionnels
            if (isset($data['description'])) {
                $ride->setDescription($data['description']);
            }
            if (isset($data['originLatLng'])) {
                $ride->setOriginLatLng($data['originLatLng']);
            }
            if (isset($data['destinationLatLng'])) {
                $ride->setDestinationLatLng($data['destinationLatLng']);
            }
            if (isset($data['estimatedDistance'])) {
                $ride->setEstimatedDistance((int)$data['estimatedDistance']);
            }
            if (isset($data['estimatedDuration'])) {
                $ride->setEstimatedDuration((int)$data['estimatedDuration']);
            }
            if (isset($data['waypoints'])) {
                $ride->setWaypoints($data['waypoints']);
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
        description: 'Met à jour les informations d\'un trajet'
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
        description: 'Marque un trajet comme démarré et change son statut'
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
        description: 'Annule un trajet et change son statut'
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
            'departureTime' => $ride->getDepartureTime()?->format('Y-m-d H:i:s'),
            'availableSeats' => $ride->getAvailableSeats(),
            'remainingSeats' => $ride->getRemainingSeats(),
            'price' => $ride->getPrice(),
            'description' => $ride->getDescription(),
            'status' => $ride->getStatus(),
            'originLatLng' => $ride->getOriginLatLng(),
            'destinationLatLng' => $ride->getDestinationLatLng(),
            'estimatedDistance' => $ride->getEstimatedDistance(),
            'estimatedDuration' => $ride->getEstimatedDuration(),
            'waypoints' => $ride->getWaypoints(),
            'conditions' => $ride->getConditions(),
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
