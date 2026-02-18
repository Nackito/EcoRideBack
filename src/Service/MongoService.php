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

        // Options TLS pour Atlas (Windows peut nécessiter un CA explicite)
        $options = [];
        $this->client = new Client($uri, $options);
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
        $cursor = $this->db->command(['ping' => 1]);
        $doc = $cursor->toArray()[0] ?? null;
        if ($doc === null) {
            return [];
        }
        return json_decode(json_encode($doc), true) ?? [];
    }

    public function ensureIndexes(): void
    {
        // TTL index pour journaux et avis bruts (30 jours)
        $ttlSeconds = 30 * 24 * 3600;

        $eventLogs = $this->getCollection('event_logs');
        $eventLogs->createIndex(['ts' => 1], ['expireAfterSeconds' => $ttlSeconds]);

        $rawReviews = $this->getCollection('raw_reviews');
        $rawReviews->createIndex(['ts' => 1], ['expireAfterSeconds' => $ttlSeconds]);

        // Index utiles (non TTL)
        $prefs = $this->getCollection('custom_driver_prefs');
        $prefs->createIndex(['userId' => 1]);

        $adminStatsDaily = $this->getCollection('admin_stats_daily');
        $adminStatsDaily->createIndex(['date' => 1], ['unique' => true]);

        // Profil utilisateur (infos personnelles)
        $userProfile = $this->getCollection('user_profile_extra');
        $userProfile->createIndex(['userId' => 1], ['unique' => true]);
    }

    public function insertLog(string $level, string $message, array $context = []): void
    {
        $this->getCollection('event_logs')->insertOne([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ts' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000))
        ]);
    }
}
