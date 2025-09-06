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
        
        // スペース区切りで検索語を分割（AND検索用）
        $searchTerms = array_filter(explode(' ', $normalizedQuery));
        
        if (empty($searchTerms)) {
            return ['results' => [], 'total' => 0];
        }
        
        // 単一検索語の場合は従来の処理
        if (count($searchTerms) === 1) {
            return searchSingleTerm($searchTerms[0], $limit, $offset);
        }
        
        // 複数検索語の場合は高速AND検索（厳密版）
        return searchMultipleTermsStrict($searchTerms, $limit, $offset);
        
    } catch (Exception $e) {
        error_log("Supabase API search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 単一検索語での検索（従来の処理）
 */
function searchSingleTerm($query, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // Supabase REST API の検索エンドポイント
        $url = $supabase_url . '/rest/v1/buildings_table_2';
        
        // 日本語と英語の両方で検索
        $japaneseTerm = $query;
        $englishTerm = strtolower($query);
        $upperTerm = strtoupper($query);
        
        // 日本語の文字変換（ひらがな・カタカナ・漢字）
        $hiraganaTerm = '';
        $katakanaTerm = '';
        if (preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $query)) {
            // ひらがなをカタカナに変換
            $hiraganaTerm = mb_convert_kana($query, 'K');
            // カタカナをひらがなに変換
            $katakanaTerm = mb_convert_kana($query, 'H');
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
            error_log("=== SINGLE TERM SEARCH DEBUG ===");
            error_log("Query: " . $query);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // タイムアウトを15秒に短縮
        
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
            return searchBuildingsIndividual($query, $limit, $offset);
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
        error_log("Single term search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 厳密AND検索（高速かつ正確）
 */
function searchMultipleTermsStrict($searchTerms, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // デバッグ情報
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("=== STRICT AND SEARCH DEBUG ===");
            error_log("Search terms: " . implode(', ', $searchTerms));
        }
        
        // 最初の検索語で検索（制限を適度に設定）
        $firstTerm = $searchTerms[0];
        $searchResults = searchSingleTerm($firstTerm, 50, 0); // 50件に制限
        
        if (empty($searchResults['results'])) {
            return ['results' => [], 'total' => 0];
        }
        
        // 残りの検索語で結果を厳密にフィルタリング
        $filteredResults = [];
        foreach ($searchResults['results'] as $result) {
            $allTermsMatch = true;
            
            // 全ての検索語が結果に含まれているかチェック
            foreach ($searchTerms as $term) {
                $termFound = false;
                
                // 各フィールドで検索語をチェック（大文字小文字を区別しない）
                $fieldsToCheck = [
                    $result['title'] ?? '',
                    $result['titleEn'] ?? '',
                    $result['buildingTypes'] ?? '',
                    $result['buildingTypesEn'] ?? '',
                    $result['location'] ?? '',
                    $result['locationEn_from_datasheetChunkEn'] ?? ''
                ];
                
                foreach ($fieldsToCheck as $fieldValue) {
                    if (stripos($fieldValue, $term) !== false) {
                        $termFound = true;
                        break;
                    }
                }
                
                if (!$termFound) {
                    $allTermsMatch = false;
                    break;
                }
            }
            
            if ($allTermsMatch) {
                $filteredResults[] = $result;
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($filteredResults, $offset, $limit);
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Strict AND search found " . count($filteredResults) . " total results");
            error_log("Returning " . count($paginatedResults) . " results (offset: $offset, limit: $limit)");
        }
        
        return [
            'results' => $paginatedResults,
            'total' => count($filteredResults)
        ];
        
    } catch (Exception $e) {
        error_log("Strict AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 高速AND検索（検索語を結合して単一クエリで実行）
 */
function searchMultipleTermsFast($searchTerms, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // デバッグ情報
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("=== FAST AND SEARCH DEBUG ===");
            error_log("Search terms: " . implode(', ', $searchTerms));
        }
        
        // 検索語を結合して単一のクエリとして実行
        $combinedQuery = implode(' ', $searchTerms);
        
        // 単一検索語として処理（既存の高速な処理を利用）
        $results = searchSingleTerm($combinedQuery, $limit, $offset);
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Fast AND search found " . count($results['results']) . " results");
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Fast AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 簡易AND検索（パフォーマンス重視）
 */
function searchMultipleTermsSimple($searchTerms, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // デバッグ情報
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("=== SIMPLE AND SEARCH DEBUG ===");
            error_log("Search terms: " . implode(', ', $searchTerms));
        }
        
        // 最初の検索語で検索を実行（制限を大きくして多くの結果を取得）
        $firstTerm = $searchTerms[0];
        $searchResults = searchSingleTerm($firstTerm, 200, 0);
        
        if (empty($searchResults['results'])) {
            return ['results' => [], 'total' => 0];
        }
        
        // 残りの検索語で結果をフィルタリング
        $filteredResults = [];
        foreach ($searchResults['results'] as $result) {
            $allTermsMatch = true;
            
            // 全ての検索語が結果に含まれているかチェック
            foreach ($searchTerms as $term) {
                $termFound = false;
                
                // 各フィールドで検索語をチェック
                $fieldsToCheck = [
                    $result['title'] ?? '',
                    $result['titleEn'] ?? '',
                    $result['buildingTypes'] ?? '',
                    $result['buildingTypesEn'] ?? '',
                    $result['location'] ?? '',
                    $result['locationEn_from_datasheetChunkEn'] ?? ''
                ];
                
                foreach ($fieldsToCheck as $fieldValue) {
                    if (stripos($fieldValue, $term) !== false) {
                        $termFound = true;
                        break;
                    }
                }
                
                if (!$termFound) {
                    $allTermsMatch = false;
                    break;
                }
            }
            
            if ($allTermsMatch) {
                $filteredResults[] = $result;
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($filteredResults, $offset, $limit);
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Simple AND search found " . count($filteredResults) . " total results");
            error_log("Returning " . count($paginatedResults) . " results (offset: $offset, limit: $limit)");
        }
        
        return [
            'results' => $paginatedResults,
            'total' => count($filteredResults)
        ];
        
    } catch (Exception $e) {
        error_log("Simple AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 複数検索語でのAND検索（元の実装）
 */
function searchMultipleTerms($searchTerms, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // デバッグ情報
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("=== MULTIPLE TERMS AND SEARCH DEBUG ===");
            error_log("Search terms: " . implode(', ', $searchTerms));
        }
        
        // タイムアウト制御
        $startTime = time();
        $maxExecutionTime = 25; // 25秒でタイムアウト
        
        // 各検索語に対して検索を実行
        $allResults = [];
        $buildingIdCounts = [];
        
        foreach ($searchTerms as $term) {
            // タイムアウトチェック
            if (time() - $startTime > $maxExecutionTime) {
                error_log("AND search timeout after " . (time() - $startTime) . " seconds");
                break;
            }
            
            $termResults = searchSingleTerm($term, 50, 0); // 制限を50件にさらに削減
            
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("Term '$term' found " . count($termResults['results']) . " results");
            }
            
            foreach ($termResults['results'] as $result) {
                $buildingId = $result['building_id'];
                
                if (!isset($buildingIdCounts[$buildingId])) {
                    $buildingIdCounts[$buildingId] = 0;
                    $allResults[$buildingId] = $result;
                }
                $buildingIdCounts[$buildingId]++;
            }
        }
        
        // 全ての検索語がマッチした結果のみを抽出（AND検索）
        $finalResults = [];
        $requiredMatches = count($searchTerms);
        
        foreach ($buildingIdCounts as $buildingId => $matchCount) {
            if ($matchCount >= $requiredMatches) {
                $finalResults[] = $allResults[$buildingId];
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($finalResults, $offset, $limit);
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("AND search found " . count($finalResults) . " total results");
            error_log("Returning " . count($paginatedResults) . " results (offset: $offset, limit: $limit)");
        }
        
        return [
            'results' => $paginatedResults,
            'total' => count($finalResults)
        ];
        
    } catch (Exception $e) {
        error_log("Multiple terms search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（フォールバック用）
 */
function searchBuildingsIndividual($query, $limit = 10, $offset = 0) {
    global $supabase_url, $supabase_key;
    
    try {
        // スペース区切りで検索語を分割（AND検索用）
        $searchTerms = array_filter(explode(' ', $query));
        
        if (empty($searchTerms)) {
            return ['results' => [], 'total' => 0];
        }
        
        // 単一検索語の場合は従来の処理
        if (count($searchTerms) === 1) {
            return searchIndividualSingleTerm($searchTerms[0], $limit, $offset);
        }
        
        // 複数検索語の場合は厳密AND検索
        return searchIndividualMultipleTermsStrict($searchTerms, $limit, $offset);
        
    } catch (Exception $e) {
        error_log("Individual search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（単一検索語）
 */
function searchIndividualSingleTerm($query, $limit = 10, $offset = 0) {
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 個別検索のタイムアウトを5秒に短縮
                
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
        error_log("Individual single term search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（厳密AND検索）
 */
function searchIndividualMultipleTermsStrict($searchTerms, $limit = 10, $offset = 0) {
    try {
        // 最初の検索語で個別検索を実行（制限を適度に設定）
        $firstTerm = $searchTerms[0];
        $searchResults = searchIndividualSingleTerm($firstTerm, 50, 0); // 50件に制限
        
        if (empty($searchResults['results'])) {
            return ['results' => [], 'total' => 0];
        }
        
        // 残りの検索語で結果を厳密にフィルタリング
        $filteredResults = [];
        foreach ($searchResults['results'] as $result) {
            $allTermsMatch = true;
            
            // 全ての検索語が結果に含まれているかチェック
            foreach ($searchTerms as $term) {
                $termFound = false;
                
                // 各フィールドで検索語をチェック（大文字小文字を区別しない）
                $fieldsToCheck = [
                    $result['title'] ?? '',
                    $result['titleEn'] ?? '',
                    $result['buildingTypes'] ?? '',
                    $result['buildingTypesEn'] ?? '',
                    $result['location'] ?? '',
                    $result['locationEn_from_datasheetChunkEn'] ?? ''
                ];
                
                foreach ($fieldsToCheck as $fieldValue) {
                    if (stripos($fieldValue, $term) !== false) {
                        $termFound = true;
                        break;
                    }
                }
                
                if (!$termFound) {
                    $allTermsMatch = false;
                    break;
                }
            }
            
            if ($allTermsMatch) {
                $filteredResults[] = $result;
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($filteredResults, $offset, $limit);
        
        return [
            'results' => $paginatedResults,
            'total' => count($filteredResults)
        ];
        
    } catch (Exception $e) {
        error_log("Individual strict AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（高速AND検索）
 */
function searchIndividualMultipleTermsFast($searchTerms, $limit = 10, $offset = 0) {
    try {
        // 検索語を結合して単一のクエリとして実行
        $combinedQuery = implode(' ', $searchTerms);
        
        // 単一検索語として処理（既存の高速な処理を利用）
        $results = searchIndividualSingleTerm($combinedQuery, $limit, $offset);
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Individual fast AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（簡易AND検索）
 */
function searchIndividualMultipleTermsSimple($searchTerms, $limit = 10, $offset = 0) {
    try {
        // 最初の検索語で個別検索を実行
        $firstTerm = $searchTerms[0];
        $searchResults = searchIndividualSingleTerm($firstTerm, 200, 0);
        
        if (empty($searchResults['results'])) {
            return ['results' => [], 'total' => 0];
        }
        
        // 残りの検索語で結果をフィルタリング
        $filteredResults = [];
        foreach ($searchResults['results'] as $result) {
            $allTermsMatch = true;
            
            // 全ての検索語が結果に含まれているかチェック
            foreach ($searchTerms as $term) {
                $termFound = false;
                
                // 各フィールドで検索語をチェック
                $fieldsToCheck = [
                    $result['title'] ?? '',
                    $result['titleEn'] ?? '',
                    $result['buildingTypes'] ?? '',
                    $result['buildingTypesEn'] ?? '',
                    $result['location'] ?? '',
                    $result['locationEn_from_datasheetChunkEn'] ?? ''
                ];
                
                foreach ($fieldsToCheck as $fieldValue) {
                    if (stripos($fieldValue, $term) !== false) {
                        $termFound = true;
                        break;
                    }
                }
                
                if (!$termFound) {
                    $allTermsMatch = false;
                    break;
                }
            }
            
            if ($allTermsMatch) {
                $filteredResults[] = $result;
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($filteredResults, $offset, $limit);
        
        return [
            'results' => $paginatedResults,
            'total' => count($filteredResults)
        ];
        
    } catch (Exception $e) {
        error_log("Individual simple AND search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 個別検索（複数検索語のAND検索）
 */
function searchIndividualMultipleTerms($searchTerms, $limit = 10, $offset = 0) {
    try {
        // タイムアウト制御
        $startTime = time();
        $maxExecutionTime = 20; // 20秒でタイムアウト
        
        // 各検索語に対して個別検索を実行
        $allResults = [];
        $buildingIdCounts = [];
        
        foreach ($searchTerms as $term) {
            // タイムアウトチェック
            if (time() - $startTime > $maxExecutionTime) {
                error_log("Individual AND search timeout after " . (time() - $startTime) . " seconds");
                break;
            }
            
            $termResults = searchIndividualSingleTerm($term, 50, 0); // 制限を50件に削減
            
            foreach ($termResults['results'] as $result) {
                $buildingId = $result['building_id'];
                
                if (!isset($buildingIdCounts[$buildingId])) {
                    $buildingIdCounts[$buildingId] = 0;
                    $allResults[$buildingId] = $result;
                }
                $buildingIdCounts[$buildingId]++;
            }
        }
        
        // 全ての検索語がマッチした結果のみを抽出（AND検索）
        $finalResults = [];
        $requiredMatches = count($searchTerms);
        
        foreach ($buildingIdCounts as $buildingId => $matchCount) {
            if ($matchCount >= $requiredMatches) {
                $finalResults[] = $allResults[$buildingId];
            }
        }
        
        // オフセットとリミットを適用
        $paginatedResults = array_slice($finalResults, $offset, $limit);
        
        return [
            'results' => $paginatedResults,
            'total' => count($finalResults)
        ];
        
    } catch (Exception $e) {
        error_log("Individual multiple terms search failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}


/**
 * 建築家情報を取得
 */
function getArchitectsForBuilding($buildingId) {
    global $supabase_url, $supabase_key;
    
    try {
        // 正しいテーブル結合で建築家情報を取得
        // buildings_table_2.building_id → building_architects.architect_id → architect_compositions.individual_architect_id → individual_architects.name_ja, name_en
        
        // まず building_architects から architect_id を取得
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
        
        $buildingArchitects = json_decode($response, true);
        
        if (empty($buildingArchitects)) {
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("No building architects found for building_id: " . $buildingId);
            }
            return [];
        }
        
        // architect_id のリストを取得
        $architectIds = array_column($buildingArchitects, 'architect_id');
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Found architect_ids: " . implode(',', $architectIds));
        }
        
        // architect_compositions から individual_architect_id を取得
        $url = $supabase_url . '/rest/v1/architect_compositions';
        $params = [
            'select' => 'individual_architect_id,order_index',
            'architect_id' => 'in.(' . implode(',', $architectIds) . ')',
            'order' => 'order_index.asc'
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
        
        $compositions = json_decode($response, true);
        
        if (empty($compositions)) {
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("No architect compositions found for architect_ids: " . implode(',', $architectIds));
            }
            return [];
        }
        
        // individual_architect_id のリストを取得
        $individualArchitectIds = array_column($compositions, 'individual_architect_id');
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Found individual_architect_ids: " . implode(',', $individualArchitectIds));
        }
        
        // individual_architects から建築家の詳細情報を取得
        $url = $supabase_url . '/rest/v1/individual_architects';
        $params = [
            'select' => 'individual_architect_id,name_ja,name_en,slug',
            'individual_architect_id' => 'in.(' . implode(',', $individualArchitectIds) . ')'
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
        
        $architects = json_decode($response, true);
        
        if (empty($architects)) {
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("No individual architects found for individual_architect_ids: " . implode(',', $individualArchitectIds));
            }
            return [];
        }
        
        if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
            error_log("Found architects: " . json_encode($architects, JSON_UNESCAPED_UNICODE));
        }
        
        // order_index に基づいてソート
        $architectMap = [];
        foreach ($architects as $architect) {
            if (isset($architect['individual_architect_id'])) {
                $architectMap[$architect['individual_architect_id']] = $architect;
            } else {
                if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                    error_log("Missing individual_architect_id in architect data: " . json_encode($architect, JSON_UNESCAPED_UNICODE));
                }
            }
        }
        
        $sortedArchitects = [];
        foreach ($compositions as $composition) {
            $individualId = $composition['individual_architect_id'];
            if (isset($architectMap[$individualId])) {
                $sortedArchitects[] = $architectMap[$individualId];
            }
        }
        
        return $sortedArchitects;
        
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
