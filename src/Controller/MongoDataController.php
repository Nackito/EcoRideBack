<?php

namespace App\Controller;

use App\Service\MongoService;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_mongo_')]
class MongoDataController extends AbstractController
{
    public function __construct(private MongoService $mongo)
    {
    }

    // Logs
    #[Route('/logs', name: 'logs_create', methods: ['POST'])]
    public function createLog(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $level = $data['level'] ?? 'info';
        $message = $data['message'] ?? '';
        $context = $data['context'] ?? [];

        if ($message === '') {
            return $this->json(['error' => 'message requis'], 400);
        }

        $this->mongo->insertLog($level, $message, is_array($context) ? $context : []);
        return $this->json(['status' => 'ok']);
    }

    // Pending reviews
    #[Route('/reviews/pending', name: 'reviews_pending_create', methods: ['POST'])]
    public function createPendingReview(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        foreach (['tripId','reviewerId','driverId','rating'] as $field) {
            if (!isset($data[$field])) {
                return $this->json(['error' => "champ '$field' requis"], 400);
            }
        }
        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            return $this->json(['error' => 'rating doit être entre 1 et 5'], 400);
        }

        $doc = [
            'tripId' => (int) $data['tripId'],
            'reviewerId' => (int) $data['reviewerId'],
            'driverId' => (int) $data['driverId'],
            'rating' => $rating,
            'comment' => $data['comment'] ?? null,
            'ts' => new UTCDateTime((int)(microtime(true) * 1000)),
        ];
        $col = $this->mongo->getCollection('reviews_pending');
        $ins = $col->insertOne($doc);
        return $this->json(['insertedId' => (string) $ins->getInsertedId()]);
    }

    #[Route('/reviews/pending', name: 'reviews_pending_list', methods: ['GET'])]
    public function listPendingReviews(Request $request): JsonResponse
    {
        $tripId = $request->query->getInt('tripId', 0);
        $filter = $tripId ? ['tripId' => $tripId] : [];
        $docs = $this->mongo->getCollection('reviews_pending')->find($filter)->toArray();
        // Convert UTCDateTime to ms
        $res = array_map(function ($d) {
            $d['ts'] = isset($d['ts']) && $d['ts'] instanceof UTCDateTime ? $d['ts']->toDateTime()->format('c') : null;
            $d['_id'] = (string) $d['_id'];
            return $d;
        }, $docs);
        return $this->json($res);
    }

    // Advanced preferences
    #[Route('/preferences/advanced', name: 'preferences_adv_upsert', methods: ['POST'])]
    public function upsertPreferences(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (!isset($data['userId']) || !isset($data['prefs']) || !is_array($data['prefs'])) {
            return $this->json(['error' => 'userId et prefs requis'], 400);
        }
        $userId = (int) $data['userId'];
        $prefs = $data['prefs'];
        $col = $this->mongo->getCollection('preferences_adv');
        $col->updateOne(
            ['userId' => $userId],
            ['$set' => ['prefs' => $prefs, 'updatedAt' => new UTCDateTime((int)(microtime(true) * 1000))]],
            ['upsert' => true]
        );
        return $this->json(['status' => 'ok']);
    }

    #[Route('/preferences/advanced/{userId}', name: 'preferences_adv_get', methods: ['GET'])]
    public function getPreferences(int $userId): JsonResponse
    {
        $doc = $this->mongo->getCollection('preferences_adv')->findOne(['userId' => $userId]);
        if (!$doc) {
            return $this->json(['error' => 'not found'], 404);
        }
        $doc['_id'] = (string) $doc['_id'];
        return $this->json($doc);
    }

    // Daily stats
    #[Route('/stats/daily', name: 'stats_daily_upsert', methods: ['POST'])]
    public function upsertDailyStats(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $date = $data['date'] ?? null; // format YYYY-MM-DD
        $metrics = $data['metrics'] ?? [];
        if (!$date || !is_array($metrics)) {
            return $this->json(['error' => 'date et metrics requis'], 400);
        }
        $col = $this->mongo->getCollection('daily_stats');
        $col->updateOne(
            ['date' => $date],
            ['$set' => ['metrics' => $metrics, 'generatedAt' => new UTCDateTime((int)(microtime(true) * 1000))]],
            ['upsert' => true]
        );
        return $this->json(['status' => 'ok']);
    }

    #[Route('/stats/daily/{date}', name: 'stats_daily_get', methods: ['GET'])]
    public function getDailyStats(string $date): JsonResponse
    {
        $doc = $this->mongo->getCollection('daily_stats')->findOne(['date' => $date]);
        if (!$doc) {
            return $this->json(['error' => 'not found'], 404);
        }
        $doc['_id'] = (string) $doc['_id'];
        return $this->json($doc);
    }
}
