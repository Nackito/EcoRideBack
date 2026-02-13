<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;

class MongoService
{
    private Client $client;
    private Database $db;

    public function __construct(string $mongodbUri = null, string $mongodbDb = null)
    {
        $uri = $mongodbUri ?? ($_ENV['MONGODB_URI'] ?? 'mongodb://127.0.0.1:27017');
        $dbName = $mongodbDb ?? ($_ENV['MONGODB_DB'] ?? 'ecoride');

        $this->client = new Client($uri);
        $this->db = $this->client->selectDatabase($dbName);
    }

    public function getDatabase(): Database
    {
        return $this->db;
    }

    public function getCollection(string $name): Collection
    {
        return $this->db->selectCollection($name);
    }

    public function ping(): array
    {
        return $this->db->command(['ping' => 1])->toArray()[0] ?? [];
    }

    public function ensureIndexes(): void
    {
        // TTL index pour logs et avis en attente (30 jours)
        $ttlSeconds = 30 * 24 * 3600;

        $logs = $this->getCollection('logs');
        $logs->createIndex(['ts' => 1], ['expireAfterSeconds' => $ttlSeconds]);

        $reviewsPending = $this->getCollection('reviews_pending');
        $reviewsPending->createIndex(['ts' => 1], ['expireAfterSeconds' => $ttlSeconds]);
    }

    public function insertLog(string $level, string $message, array $context = []): void
    {
        $this->getCollection('logs')->insertOne([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ts' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000))
        ]);
    }
}
