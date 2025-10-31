<?php
/**
 * APIガード（CORS、認証チェック）
 *
 * 全APIエンドポイントでincludeされるセキュリティレイヤー
 */

// 同一オリジンチェック
function checkSameOrigin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expectedOrigin = $scheme . '://' . $host;

    // OPTIONSリクエスト（プリフライト）
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($origin === $expectedOrigin || empty($origin)) {
            header('Access-Control-Allow-Origin: ' . $expectedOrigin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        } else {
            http_response_code(403);
            exit;
        }
    }

    // 実リクエスト
    if (!empty($origin) && $origin !== $expectedOrigin) {
        logError('CORS violation', ['origin' => $origin, 'expected' => $expectedOrigin]);
        jsonError('CORS_ERROR', 'アクセスが拒否されました', 403);
    }

    // 同一オリジンならCORSヘッダーを付与
    header('Access-Control-Allow-Origin: ' . $expectedOrigin);
    header('Access-Control-Allow-Credentials: true');
}

// GETメソッドのみ許可
function allowOnlyGet(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('METHOD_NOT_ALLOWED', 'GETメソッドのみ許可されています', 405);
    }
}

// セキュリティヘッダーを設定
function setSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ガード実行
checkSameOrigin();
allowOnlyGet();
setSecurityHeaders();
