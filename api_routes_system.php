<?php
/**
 * Core/system routes for API health and platform controls.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\FeatureFlag;
use DotProject\Core\LegacyAdapter;
use DotProject\Service\AuthorizationService;

$router->get('/', function (Request $req, Response $res) {
    return $res->json([
        'name' => 'dotProject API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

$router->get('/v1/health', function (Request $req, Response $res) {
    return $res->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
    ]);
});

$ensureAdmin = static function (Request $req, Response $res): ?Response {
    $userId = $req->getParam('_user_id');
    if ($userId === null) {
        return $res->unauthorized();
    }

    if (!AuthorizationService::getInstance()->isAdmin((int) $userId)) {
        return $res->forbidden('Admin privileges required');
    }

    return null;
};

$router->get('/v1/cache/stats', function (Request $req, Response $res) use ($ensureAdmin) {
    if ($guard = $ensureAdmin($req, $res)) {
        return $guard;
    }

    $cache = new Cache();
    return $res->json($cache->getStats());
});

$router->delete('/v1/cache/clear', function (Request $req, Response $res) use ($ensureAdmin) {
    if ($guard = $ensureAdmin($req, $res)) {
        return $guard;
    }

    $cache = new Cache();
    $result = $cache->clear();
    return $res->json([
        'success' => $result,
        'message' => $result ? 'Cache cleared' : 'Failed to clear cache',
    ]);
});

$router->get('/v1/features', function (Request $req, Response $res) {
    $features = FeatureFlag::getInstance();
    return $res->json([
        'features' => $features->getAllFeatures(),
    ]);
});

$router->get('/v1/features/{name}', function (Request $req, Response $res) {
    $userId = $req->getParam('_user_id');
    $featureName = $req->getParam('name');
    $features = FeatureFlag::getInstance();

    return $res->json([
        'feature' => $featureName,
        'enabled' => $features->isEnabled($featureName, $userId),
        'user_id' => $userId,
    ]);
});

$router->get('/v1/migration/status', function (Request $req, Response $res) {
    return $res->json([
        'modules' => LegacyAdapter::getMigrationStatus(),
        'overall_progress' => '80%',
    ]);
});
