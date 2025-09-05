<?php
/**
 * 安全なデータベース接続ファイル（テスト用）
 * データベース接続エラーを回避
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// データベース接続を無効化（テスト用）
$pdo = null;

/**
 * 検索用のSQLクエリを実行する関数（モック版）
 */
function searchBuildings($pdo, $query, $limit = 10, $offset = 0) {
    // モックデータを返す
    return [
        'results' => [
            [
                'building_id' => 1,
                'slug' => 'test-building',
                'title' => 'テスト建築物',
                'titleEn' => 'Test Building',
                'buildingTypes' => 'テスト, 建築',
                'buildingTypesEn' => 'Test, Architecture',
                'location' => 'テスト場所',
                'locationEn_from_datasheetChunkEn' => 'Test Location',
                'completionYears' => '2023',
                'lat' => 35.6762,
                'lng' => 139.6503,
                'architects' => [
                    ['name_ja' => 'テスト建築家', 'name_en' => 'Test Architect', 'slug' => 'test-architect']
                ],
                'rank' => 0.95
            ]
        ],
        'total' => 1
    ];
}

/**
 * 建築家情報を取得する関数（モック版）
 */
function getArchitectBySlug($pdo, $slug) {
    return [
        'individual_architect_id' => 1,
        'name_ja' => 'テスト建築家',
        'name_en' => 'Test Architect',
        'slug' => $slug,
        'birth_year' => '1950',
        'death_year' => null,
        'biography' => 'テスト建築家の略歴です。',
        'awards' => 'テスト賞を受賞',
        'buildings' => json_encode([
            [
                'building_id' => 1,
                'slug' => 'test-building',
                'title' => 'テスト建築物',
                'titleEn' => 'Test Building',
                'completionYears' => '2023',
                'location' => 'テスト場所'
            ]
        ])
    ];
}

/**
 * 建築物情報を取得する関数（モック版）
 */
function getBuildingBySlug($pdo, $slug) {
    return [
        'building_id' => 1,
        'slug' => $slug,
        'title' => 'テスト建築物',
        'titleEn' => 'Test Building',
        'buildingTypes' => 'テスト, 建築',
        'buildingTypesEn' => 'Test, Architecture',
        'location' => 'テスト場所',
        'locationEn_from_datasheetChunkEn' => 'Test Location',
        'completionYears' => '2023',
        'lat' => 35.6762,
        'lng' => 139.6503,
        'description' => 'テスト建築物の詳細説明です。',
        'history' => 'テスト建築物の歴史です。',
        'technical_info' => 'テスト建築物の技術情報です。',
        'architects' => json_encode([
            ['name_ja' => 'テスト建築家', 'name_en' => 'Test Architect', 'slug' => 'test-architect']
        ])
    ];
}

/**
 * エスケープ関数（XSS対策）
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * JSONレスポンスを返す関数
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
