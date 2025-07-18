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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/cars', name: 'api_cars_')]
class CarController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CarRepository $carRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    /*#[OA\Get(
        path: '/api/cars',
        summary: 'Récupérer tous les véhicules',
        description: 'Retourne la liste de tous les véhicules'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des véhicules récupérée avec succès',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Car')
        )
    )]*/
    public function list(): JsonResponse
    {
        $cars = $this->carRepository->findAll();

        $data = array_map(function (Car $car) {
            return [
                'id' => $car->getId(),
                'modele' => $car->getModele(),
                'immatriculation' => $car->getImmatriculation(),
                'energie' => $car->getEnergie(),
                'color' => $car->getColor(),
                'dateFirstImmatriculation' => $car->getDateFirstImmatriculation()?->format('Y-m-d'),
                'createdAt' => $car->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $cars);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    /*#[OA\Get(
        path: '/api/cars/{id}',
        summary: 'Récupérer un véhicule par ID',
        description: 'Retourne les détails d\'un véhicule spécifique'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du véhicule',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule récupéré avec succès',
        content: new OA\JsonContent(ref: '#/components/schemas/Car')
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]*/
    public function show(int $id): JsonResponse
    {
        $car = $this->carRepository->find($id);

        if (!$car) {
            return $this->json(['error' => 'Véhicule non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $car->getId(),
            'modele' => $car->getModele(),
            'immatriculation' => $car->getImmatriculation(),
            'energie' => $car->getEnergie(),
            'color' => $car->getColor(),
            'dateFirstImmatriculation' => $car->getDateFirstImmatriculation()?->format('Y-m-d'),
            'createdAt' => $car->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cars',
        summary: 'Créer un nouveau véhicule',
        description: 'Ajoute un nouveau véhicule à la base de données'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['modele', 'immatriculation', 'energie', 'color'],
            properties: [
                new OA\Property(property: 'modele', type: 'string', description: 'Modèle du véhicule', example: 'Peugeot 308'),
                new OA\Property(property: 'immatriculation', type: 'string', description: 'Numéro d\'immatriculation', example: 'AB-123-CD'),
                new OA\Property(property: 'energie', type: 'string', description: 'Type d\'énergie', example: 'Essence'),
                new OA\Property(property: 'color', type: 'string', description: 'Couleur du véhicule', example: 'Bleu'),
                new OA\Property(property: 'dateFirstImmatriculation', type: 'string', format: 'date', description: 'Date de première immatriculation', nullable: true, example: '2020-01-15'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Véhicule créé avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'modele', type: 'string', example: 'Peugeot 308'),
                new OA\Property(property: 'immatriculation', type: 'string', example: 'AB-123-CD'),
                new OA\Property(property: 'energie', type: 'string', example: 'Essence'),
                new OA\Property(property: 'color', type: 'string', example: 'Bleu'),
                new OA\Property(property: 'dateFirstImmatriculation', type: 'string', format: 'date', example: '2020-01-15'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-01-01T10:00:00')
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
                'details' => json_last_error_msg(),
                'received_content' => $content
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier les champs requis
        $requiredFields = ['modele', 'immatriculation', 'energie', 'color'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier l'unicité de l'immatriculation
        $existingCar = $this->carRepository->findOneBy(['immatriculation' => $data['immatriculation']]);
        if ($existingCar) {
            return $this->json(['error' => 'Un véhicule avec cette immatriculation existe déjà'], Response::HTTP_CONFLICT);
        }

        try {
            $car = new Car();
            $car->setModele($data['modele']);
            $car->setImmatriculation($data['immatriculation']);
            $car->setEnergie($data['energie']);
            $car->setColor($data['color']);

            if (isset($data['dateFirstImmatriculation']) && !empty($data['dateFirstImmatriculation'])) {
                try {
                    $car->setDateFirstImmatriculation(new \DateTime($data['dateFirstImmatriculation']));
                } catch (\Exception $e) {
                    return $this->json(['error' => 'Format de date invalide pour dateFirstImmatriculation'], Response::HTTP_BAD_REQUEST);
                }
            }

            // Valider l'entité
            $errors = $this->validator->validate($car);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($car);
            $this->entityManager->flush();

            return $this->json([
                'id' => $car->getId(),
                'modele' => $car->getModele(),
                'immatriculation' => $car->getImmatriculation(),
                'energie' => $car->getEnergie(),
                'color' => $car->getColor(),
                'dateFirstImmatriculation' => $car->getDateFirstImmatriculation()?->format('Y-m-d'),
                'createdAt' => $car->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création du véhicule',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    /*#[OA\Put(
        path: '/api/cars/{id}',
        summary: 'Modifier un véhicule',
        description: 'Met à jour les informations d\'un véhicule'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du véhicule',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'modele', type: 'string', description: 'Modèle du véhicule'),
                new OA\Property(property: 'immatriculation', type: 'string', description: 'Numéro d\'immatriculation'),
                new OA\Property(property: 'energie', type: 'string', description: 'Type d\'énergie'),
                new OA\Property(property: 'color', type: 'string', description: 'Couleur du véhicule'),
                new OA\Property(property: 'dateFirstImmatriculation', type: 'string', format: 'date', description: 'Date de première immatriculation', nullable: true),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule modifié avec succès',
        content: new OA\JsonContent(ref: '#/components/schemas/Car')
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    #[OA\Response(
        response: 409,
        description: 'Immatriculation déjà utilisée'
    )]*/
    public function update(int $id, Request $request): JsonResponse
    {
        $car = $this->carRepository->find($id);

        if (!$car) {
            return $this->json(['error' => 'Véhicule non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'immatriculation existe déjà (sauf pour le véhicule actuel)
        if (isset($data['immatriculation']) && $data['immatriculation'] !== $car->getImmatriculation()) {
            $existingCar = $this->carRepository->findOneBy(['immatriculation' => $data['immatriculation']]);
            if ($existingCar) {
                return $this->json(['error' => 'Un véhicule avec cette immatriculation existe déjà'], Response::HTTP_CONFLICT);
            }
        }

        if (isset($data['modele'])) {
            $car->setModele($data['modele']);
        }
        if (isset($data['immatriculation'])) {
            $car->setImmatriculation($data['immatriculation']);
        }
        if (isset($data['energie'])) {
            $car->setEnergie($data['energie']);
        }
        if (isset($data['color'])) {
            $car->setColor($data['color']);
        }
        if (isset($data['dateFirstImmatriculation'])) {
            $car->setDateFirstImmatriculation(new \DateTime($data['dateFirstImmatriculation']));
        }

        $errors = $this->validator->validate($car);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $car->getId(),
            'modele' => $car->getModele(),
            'immatriculation' => $car->getImmatriculation(),
            'energie' => $car->getEnergie(),
            'color' => $car->getColor(),
            'dateFirstImmatriculation' => $car->getDateFirstImmatriculation()?->format('Y-m-d'),
            'createdAt' => $car->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
