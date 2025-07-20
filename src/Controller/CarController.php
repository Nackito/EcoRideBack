<?php

namespace App\Controller;

use App\Entity\Car;
use App\Repository\CarRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/car', name: 'app_api_car_')]
class CarController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CarRepository $repository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/car',
        summary: 'Create a new Car',
        description: 'Create a new car with the provided data',
        tags: ['Car'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['modele', 'immatriculation', 'energie'],
                properties: [
                    new OA\Property(property: 'modele', type: 'string', example: 'Tesla Model S'),
                    new OA\Property(property: 'immatriculation', type: 'string', example: 'AB-123-CD'),
                    new OA\Property(property: 'energie', type: 'string', enum: ['Essence', 'Diesel', 'Electrique', 'Hybride'], example: 'Electrique'),
                    new OA\Property(property: 'color', type: 'string', example: 'Rouge'),
                    new OA\Property(property: 'dateFirstImmatriculation', type: 'string', format: 'date', example: '2023-01-01'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Car created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Car created successfully'),
                        new OA\Property(
                            property: 'car',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'modele', type: 'string', example: 'Tesla Model S'),
                                new OA\Property(property: 'immatriculation', type: 'string', example: 'AB-123-CD'),
                                new OA\Property(property: 'energie', type: 'string', example: 'Electrique'),
                                new OA\Property(property: 'color', type: 'string', example: 'Rouge'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation errors'),
            new OA\Response(response: 409, description: 'Car with this immatriculation already exists')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $this->validateRequestData($request);
        if ($data instanceof JsonResponse) {
            return $data; // Return error response
        }

        // Vérifier que l'immatriculation n'existe pas déjà
        $existingCar = $this->repository->findOneBy(['immatriculation' => $data['immatriculation']]);
        if ($existingCar) {
            return $this->json([
                'success' => false,
                'error' => 'A car with this immatriculation already exists'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $car = $this->createCarFromData($data);

            // Validation
            $errors = $this->validator->validate($car);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errorMessages
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->manager->persist($car);
            $this->manager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Car created successfully',
                'car' => $this->formatCarResponse($car)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error creating car',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/car',
        summary: 'List all Cars',
        description: 'Get a list of all cars',
        tags: ['Car'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of cars',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'cars',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'modele', type: 'string', example: 'Tesla Model S'),
                                    new OA\Property(property: 'immatriculation', type: 'string', example: 'AB-123-CD'),
                                    new OA\Property(property: 'energie', type: 'string', example: 'Electrique'),
                                    new OA\Property(property: 'color', type: 'string', example: 'Rouge'),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function list(): JsonResponse
    {
        $cars = $this->repository->findAll();

        return $this->json([
            'success' => true,
            'cars' => array_map([$this, 'formatCarResponse'], $cars)
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/car/{id}',
        summary: 'Get a Car by ID',
        description: 'Get a specific car by its ID',
        tags: ['Car'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'The ID of the car to retrieve',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Car found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'car',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'modele', type: 'string', example: 'Tesla Model S'),
                                new OA\Property(property: 'immatriculation', type: 'string', example: 'AB-123-CD'),
                                new OA\Property(property: 'energie', type: 'string', example: 'Electrique'),
                                new OA\Property(property: 'color', type: 'string', example: 'Rouge'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Car not found')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $car = $this->repository->find($id);

        if (!$car) {
            return $this->json([
                'success' => false,
                'error' => 'Car not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'car' => $this->formatCarResponse($car)
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/car/{id}',
        summary: 'Delete a Car',
        description: 'Delete a car by its ID',
        tags: ['Car'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'The ID of the car to delete',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Car deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Car deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Car not found'),
            new OA\Response(response: 500, description: 'Server error')
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        try {
            $car = $this->repository->find($id);

            if (!$car) {
                return $this->json([
                    'success' => false,
                    'error' => 'Car not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $this->manager->remove($car);
            $this->manager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Car deleted successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error deleting car',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateRequestData(Request $request): array|JsonResponse
    {
        // Vérifier le Content-Type
        if (!$request->headers->contains('Content-Type', 'application/json')) {
            return $this->json([
                'success' => false,
                'error' => 'Content-Type must be application/json'
            ], Response::HTTP_BAD_REQUEST);
        }

        $content = $request->getContent();
        if (empty($content)) {
            return $this->json([
                'success' => false,
                'error' => 'Request body is empty'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON',
                'details' => json_last_error_msg()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier les champs requis
        $requiredFields = ['modele', 'immatriculation', 'energie'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                return $this->json([
                    'success' => false,
                    'error' => "Field '$field' is required"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        return $data;
    }

    private function createCarFromData(array $data): Car
    {
        $car = new Car();
        $car->setModele($data['modele']);
        $car->setImmatriculation($data['immatriculation']);
        $car->setEnergie($data['energie']);

        // Champs optionnels
        if (isset($data['color'])) {
            $car->setColor($data['color']);
        }

        if (isset($data['dateFirstImmatriculation'])) {
            $date = new \DateTime($data['dateFirstImmatriculation']);
            $car->setDateFirstImmatriculation($date);
        }

        $car->setCreatedAt(new DateTimeImmutable());

        return $car;
    }

    private function formatCarResponse(Car $car): array
    {
        return [
            'id' => $car->getId(),
            'modele' => $car->getModele(),
            'immatriculation' => $car->getImmatriculation(),
            'energie' => $car->getEnergie(),
            'color' => $car->getColor(),
            'dateFirstImmatriculation' => $car->getDateFirstImmatriculation()?->format('Y-m-d'),
            'createdAt' => $car->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('/test', name: 'test', methods: ['GET'])]
    #[OA\Get(
        path: '/api/car/test',
        summary: 'Test endpoint to create a Car via GET',
        description: 'This endpoint creates a test car for development purposes.',
        tags: ['Car'],
    )]
    public function test(): Response
    {
        $car = new Car();
        $car->setModele('Tesla Model S (Test)');
        $car->setImmatriculation('TEST-123-XY');
        $car->setEnergie('Electrique');
        $car->setColor('Bleu');
        $car->setDateFirstImmatriculation(new \DateTime('2024-01-01'));
        $car->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($car);
        $this->manager->flush();

        return $this->json([
            'message' => "Test car created with ID {$car->getId()}",
            'car' => [
                'id' => $car->getId(),
                'modele' => $car->getModele(),
                'immatriculation' => $car->getImmatriculation(),
                'energie' => $car->getEnergie(),
                'color' => $car->getColor(),
            ]
        ]);
    }
}
