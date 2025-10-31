<?php
/**
 * 直近30分のアクティブ訪問数API
 *
 * GET /api/active_30.php
 *
 * レスポンス例:
 * {
 *   "updated_at": "2025-10-31T11:00:05+09:00",
 *   "ttl": 60,
 *   "total_active_30": 123,
 *   "by_site": [
 *     {"idSite": 1, "active_30": 20},
 *     {"idSite": 2, "active_30": 15}
 *   ]
 * }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_guard.php';

$cacheKey = 'active_30';
$ttl = $config['CACHE_TTL_ACTIVE_30'];

// キャッシュチェック
$cached = getCache($cacheKey, $ttl);
if ($cached !== null) {
    jsonSuccess($cached);
}

// 設定チェック
if (empty($config['SITE_IDS'])) {
    // 最後のキャッシュがあれば返す
    $lastCache = getLastCache($cacheKey);
    if ($lastCache !== null) {
        jsonSuccess($lastCache);
    }
    jsonError('CONFIG_ERROR', 'サイトIDが設定されていません');
}

try {
    $bySite = [];
    $totalActive = 0;

    foreach ($config['SITE_IDS'] as $idSite) {
        try {
            $data = callMatomoApi('Live.getCounters', [
                'idSite' => $idSite,
                'lastMinutes' => 30,
            ]);

            // Matomoは配列で返すことがある
            if (is_array($data) && isset($data[0])) {
                $data = $data[0];
            }

            $activeCount = 0;

            // visits または nb_visits を使用
            if (isset($data['visits'])) {
                $activeCount = (int)$data['visits'];
            } elseif (isset($data['nb_visits'])) {
                $activeCount = (int)$data['nb_visits'];
            } elseif (isset($data['actions'])) {
                $activeCount = (int)$data['actions'];
            }

            $bySite[] = [
                'idSite' => (int)$idSite,
                'active_30' => $activeCount,
            ];

            $totalActive += $activeCount;

        } catch (Exception $e) {
            logError("サイトID {$idSite} の取得失敗: " . $e->getMessage());
            // 個別サイトの失敗は無視して続行
            $bySite[] = [
                'idSite' => (int)$idSite,
                'active_30' => 0,
            ];
        }
    }

    $response = [
        'updated_at' => getCurrentTimestamp(),
        'ttl' => $ttl,
        'total_active_30' => $totalActive,
        'by_site' => $bySite,
    ];

    // キャッシュに保存
    setCache($cacheKey, $response);

    jsonSuccess($response);

} catch (Exception $e) {
    logError('active_30 API error: ' . $e->getMessage());

    // 最後のキャッシュがあれば返す（フェイルソフト）
    $lastCache = getLastCache($cacheKey);
    if ($lastCache !== null) {
        jsonSuccess($lastCache);
    }

    jsonError('UPSTREAM_ERROR', 'Matomo APIへの接続に失敗しました: ' . $e->getMessage());
}
