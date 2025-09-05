<?php
/**
 * Supabase REST API接続ファイル
 * PostgreSQL直接接続の代わりにREST APIを使用
 */

// .envファイルの読み込み
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Supabase設定
$supabase_url = $_ENV['VITE_SUPABASE_URL'] ?? $_ENV['SUPABASE_URL'] ?? '';
$supabase_key = $_ENV['VITE_SUPABASE_ANON_KEY'] ?? $_ENV['SUPABASE_ANON_KEY'] ?? '';

if (empty($supabase_url) || empty($supabase_key)) {
    die('Supabase URL または API Key が設定されていません。.envファイルを確認してください。');
}

/**
 * Supabase REST API経由で検索を実行
 */
function searchBuildings($pdo, $query, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // 検索クエリの正規化
        $normalizedQuery = preg_replace('/[\s　]+/u', ' ', trim($query));
        
        if (empty($normalizedQuery)) {
            return ['results' => [], 'total' => 0];
        }
        
        // Supabase REST API の検索エンドポイント
        $url = $supabase_url . '/rest/v1/buildings_table_2';
        
        // 検索パラメータ（Supabase REST APIの正しい構文）
        // 注意: http_build_query()が自動的にURLエンコードするため、ここではエンコードしない
        $searchTerm = $normalizedQuery;
        
        // 日本語と英語の両方で検索
        $japaneseTerm = $normalizedQuery;
        $englishTerm = strtolower($normalizedQuery);
        $upperTerm = strtoupper($normalizedQuery);
        
        // 日本語の文字変換（ひらがな・カタカナ・漢字）
        $hiraganaTerm = '';
        $katakanaTerm = '';
        if (preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $normalizedQuery)) {
            // ひらがなをカタカナに変換
            $hiraganaTerm = mb_convert_kana($normalizedQuery, 'K');
            // カタカナをひらがなに変換
            $katakanaTerm = mb_convert_kana($normalizedQuery, 'H');
        }
        
        // OR条件の構築
        $orConditions = [
            'title.ilike.%' . $japaneseTerm . '%',
            'titleEn.ilike.%' . $japaneseTerm . '%',
            'buildingTypes.ilike.%' . $japaneseTerm . '%',
            'location.ilike.%' . $japaneseTerm . '%',
            'locationEn_from_datasheetChunkEn.ilike.%' . $japaneseTerm . '%',
            'title.ilike.%' . $englishTerm . '%',
            'titleEn.ilike.%' . $englishTerm . '%',
            'buildingTypesEn.ilike.%' . $englishTerm . '%',
            'locationEn_from_datasheetChunkEn.ilike.%' . $englishTerm . '%',
            'title.ilike.%' . $upperTerm . '%',
            'titleEn.ilike.%' . $upperTerm . '%',
            'buildingTypesEn.ilike.%' . $upperTerm . '%',
            'locationEn_from_datasheetChunkEn.ilike.%' . $upperTerm . '%'
        ];
        
        // 日本語変換がある場合は追加
        if (!empty($hiraganaTerm)) {
            $orConditions[] = 'title.ilike.%' . $hiraganaTerm . '%';
            $orConditions[] = 'titleEn.ilike.%' . $hiraganaTerm . '%';
        }
        if (!empty($katakanaTerm)) {
            $orConditions[] = 'title.ilike.%' . $katakanaTerm . '%';
            $orConditions[] = 'titleEn.ilike.%' . $katakanaTerm . '%';
        }
        
        $params = [
            'select' => 'building_id,slug,title,titleEn,buildingTypes,buildingTypesEn,location,locationEn_from_datasheetChunkEn,completionYears,lat,lng',
            'or' => '(' . implode(',', $orConditions) . ')',
            'limit' => $limit,
            'offset' => $offset
        ];
        
        // デバッグ情報（開発環境のみ）
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("=== SEARCH DEBUG ===");
            error_log("Original query: " . $query);
            error_log("Normalized query: " . $normalizedQuery);
            error_log("Japanese term: " . $japaneseTerm);
            error_log("English term: " . $englishTerm);
            error_log("Upper term: " . $upperTerm);
            error_log("OR conditions: " . implode(', ', $orConditions));
            error_log("Final URL: " . $url . '?' . http_build_query($params));
        }
        
        $url .= '?' . http_build_query($params);
        
        // HTTPリクエストの実行
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            // デバッグ情報を追加
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("HTTP Error: " . $httpCode);
                error_log("Response: " . $response);
                error_log("Request URL: " . $url);
            }
            
            // ORクエリが失敗した場合、個別検索を試す
            return searchBuildingsIndividual($normalizedQuery, $limit, $offset);
        }
        
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON Decode Error: ' . json_last_error_msg());
        }
        
        // デバッグ情報（開発環境のみ）
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("HTTP Code: " . $httpCode);
            error_log("Raw response: " . substr($response, 0, 500));
            error_log("Decoded data count: " . (is_array($data) ? count($data) : 'not array'));
            if (is_array($data) && count($data) > 0) {
                error_log("First result: " . print_r($data[0], true));
            }
        }
        
        // 結果の整形
        $results = [];
        foreach ($data as $row) {
            // 建築家情報を取得（別途API呼び出し）
            $architects = getArchitectsForBuilding($row['building_id']);
            
            $results[] = [
                'building_id' => $row['building_id'],
                'slug' => $row['slug'],
                'title' => $row['title'],
                'titleEn' => $row['titleEn'],
                'buildingTypes' => $row['buildingTypes'],
                'buildingTypesEn' => $row['buildingTypesEn'],
                'location' => $row['location'],
                'locationEn_from_datasheetChunkEn' => $row['locationEn_from_datasheetChunkEn'],
                'completionYears' => $row['completionYears'],
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'architects' => $architects,
                'rank' => 1.0 // REST APIでは関連度スコアは計算しない
            ];
        }
        
        // 総件数の取得（簡易版）
        $total = count($results) + $offset;
        if (count($results) === $limit) {
            $total += 1; // 次のページがある可能性を示す
        }
        
        return [
            'results' => $results,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        error_log("Supabase API search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（フォールバック用）
 */
function searchBuildingsIndividual($query, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        $allResults = [];
        
        // 各フィールドで個別に検索
        $searchFields = ['title', 'titleEn', 'buildingTypes', 'buildingTypesEn', 'location', 'locationEn_from_datasheetChunkEn'];
        
        foreach ($searchFields as $field) {
            // 日本語、小文字英語、大文字英語で検索
            $searchTerms = [
                $query,  // 元の検索語
                strtolower($query),  // 小文字
                strtoupper($query)   // 大文字
            ];
            
            foreach ($searchTerms as $term) {
                $url = $supabase_url . '/rest/v1/buildings_table_2';
                $params = [
                    'select' => 'building_id,slug,title,titleEn,buildingTypes,buildingTypesEn,location,locationEn_from_datasheetChunkEn,completionYears,lat,lng',
                    $field . '.ilike' => '%' . $term . '%',
                    'limit' => $limit
                ];
                
                $url .= '?' . http_build_query($params);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $supabase_key,
                    'Authorization: Bearer ' . $supabase_key,
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if ($data) {
                        $allResults = array_merge($allResults, $data);
                    }
                }
            }
        }
        
        // 重複を除去
        $uniqueResults = [];
        $seenIds = [];
        foreach ($allResults as $result) {
            if (!in_array($result['building_id'], $seenIds)) {
                $uniqueResults[] = $result;
                $seenIds[] = $result['building_id'];
            }
        }
        
        // 結果の整形
        $results = [];
        foreach (array_slice($uniqueResults, $offset, $limit) as $row) {
            $architects = getArchitectsForBuilding($row['building_id']);
            
            $results[] = [
                'building_id' => $row['building_id'],
                'slug' => $row['slug'],
                'title' => $row['title'],
                'titleEn' => $row['titleEn'],
                'buildingTypes' => $row['buildingTypes'],
                'buildingTypesEn' => $row['buildingTypesEn'],
                'location' => $row['location'],
                'locationEn_from_datasheetChunkEn' => $row['locationEn_from_datasheetChunkEn'],
                'completionYears' => $row['completionYears'],
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'architects' => $architects,
                'rank' => 1.0
            ];
        }
        
        return [
            'results' => $results,
            'total' => count($uniqueResults)
        ];
        
    } catch (Exception $e) {
        error_log("Individual search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}


/**
 * 建築家情報を取得
 */
function getArchitectsForBuilding($buildingId) {
    global $supabase_url, $supabase_key;
    
    try {
        $url = $supabase_url . '/rest/v1/building_architects';
        $params = [
            'select' => 'architect_id',
            'building_id' => 'eq.' . $buildingId
        ];
        
        $url .= '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return [];
        }
        
        // 建築家の詳細情報を取得
        $architectIds = array_column($data, 'architect_id');
        return getArchitectDetails($architectIds);
        
    } catch (Exception $e) {
        error_log("Architect fetch failed: " . $e->getMessage());
        return [];
    }
}

/**
 * 建築家の詳細情報を取得
 */
function getArchitectDetails($architectIds) {
    global $supabase_url, $supabase_key;
    
    if (empty($architectIds)) {
        return [];
    }
    
    try {
        $url = $supabase_url . '/rest/v1/individual_architects';
        $params = [
            'select' => 'name_ja,name_en,slug',
            'individual_architect_id' => 'in.(' . implode(',', $architectIds) . ')'
        ];
        
        $url .= '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        return $data ?: [];
        
    } catch (Exception $e) {
        error_log("Architect details fetch failed: " . $e->getMessage());
        return [];
    }
}

/**
 * 建築物情報を取得
 */
function getBuildingBySlug($pdo, $slug) {
    global $supabase_url, $supabase_key;
    
    try {
        $url = $supabase_url . '/rest/v1/buildings_table_2';
        $params = [
            'select' => '*',
            'slug' => 'eq.' . $slug
        ];
        
        $url .= '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return null;
        }
        
        $building = $data[0];
        $building['architects'] = json_encode(getArchitectsForBuilding($building['building_id']));
        
        return $building;
        
    } catch (Exception $e) {
        error_log("Building fetch failed: " . $e->getMessage());
        return null;
    }
}

/**
 * 建築家情報を取得
 */
function getArchitectBySlug($pdo, $slug) {
    global $supabase_url, $supabase_key;
    
    try {
        $url = $supabase_url . '/rest/v1/individual_architects';
        $params = [
            'select' => '*',
            'slug' => 'eq.' . $slug
        ];
        
        $url .= '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return null;
        }
        
        return $data[0];
        
    } catch (Exception $e) {
        error_log("Architect fetch failed: " . $e->getMessage());
        return null;
    }
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

// ダミーのPDOオブジェクト（互換性のため）
$pdo = null;
?>
