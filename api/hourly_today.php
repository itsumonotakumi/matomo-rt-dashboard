<?php
/**
 * 今日の時間帯別アクセス数API
 *
 * GET /api/hourly_today.php
 *
 * レスポンス例:
 * {
 *   "updated_at": "2025-10-31T11:00:05+09:00",
 *   "ttl": 300,
 *   "hours": [0, 1, 2, ..., 23],
 *   "visits": [10, 8, 5, ..., 42],
 *   "by_site": [
 *     {"idSite": 1, "visits": [5, 3, 2, ..., 20]},
 *     {"idSite": 2, "visits": [5, 5, 3, ..., 22]}
 *   ]
 * }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_guard.php';

$cacheKey = 'hourly_today';
$ttl = $config['CACHE_TTL_HOURLY'];

// キャッシュチェック
$cached = getCache($cacheKey, $ttl);
if ($cached !== null) {
    jsonSuccess($cached);
}

// 設定チェック
if (empty($config['SITE_IDS'])) {
    $lastCache = getLastCache($cacheKey);
    if ($lastCache !== null) {
        jsonSuccess($lastCache);
    }
    jsonError('CONFIG_ERROR', 'サイトIDが設定されていません');
}

try {
    $bySite = [];
    $totalVisitsByHour = array_fill(0, 24, 0);

    foreach ($config['SITE_IDS'] as $idSite) {
        try {
            $data = callMatomoApi('VisitTime.getVisitInformationPerLocalTime', [
                'idSite' => $idSite,
                'period' => 'day',
                'date' => 'today',
            ]);

            $siteVisitsByHour = array_fill(0, 24, 0);

            // Matomoは時間ごとのデータを返す
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['label']) && isset($item['nb_visits'])) {
                        // labelは "0h-1h" 形式
                        $hour = (int)$item['label'];
                        if ($hour >= 0 && $hour < 24) {
                            $visits = (int)$item['nb_visits'];
                            $siteVisitsByHour[$hour] = $visits;
                            $totalVisitsByHour[$hour] += $visits;
                        }
                    }
                }
            }

            $bySite[] = [
                'idSite' => (int)$idSite,
                'visits' => array_values($siteVisitsByHour),
            ];

        } catch (Exception $e) {
            logError("サイトID {$idSite} の時間帯別取得失敗: " . $e->getMessage());
            // 個別サイトの失敗は無視
            $bySite[] = [
                'idSite' => (int)$idSite,
                'visits' => array_fill(0, 24, 0),
            ];
        }
    }

    $response = [
        'updated_at' => getCurrentTimestamp(),
        'ttl' => $ttl,
        'hours' => range(0, 23),
        'visits' => array_values($totalVisitsByHour),
        'by_site' => $bySite,
    ];

    // キャッシュに保存
    setCache($cacheKey, $response);

    jsonSuccess($response);

} catch (Exception $e) {
    logError('hourly_today API error: ' . $e->getMessage());

    // 最後のキャッシュがあれば返す（フェイルソフト）
    $lastCache = getLastCache($cacheKey);
    if ($lastCache !== null) {
        jsonSuccess($lastCache);
    }

    jsonError('UPSTREAM_ERROR', 'Matomo APIへの接続に失敗しました: ' . $e->getMessage());
}
