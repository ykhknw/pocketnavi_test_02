<?php
/**
 * データベース接続共通ファイル
 * Supabase PostgreSQL データベースへの接続設定
 */

// .envファイルの読み込み（存在する場合）
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// アプリケーション環境の設定
$app_env = $_ENV['APP_ENV'] ?? 'development';

// データベース接続設定
$db_config = [
    'host' => $_ENV['SUPABASE_DB_HOST'] ?? 'localhost',
    'port' => $_ENV['SUPABASE_DB_PORT'] ?? '5432',
    'dbname' => $_ENV['SUPABASE_DB_NAME'] ?? 'postgres',
    'user' => $_ENV['SUPABASE_DB_USER'] ?? 'postgres',
    'password' => $_ENV['SUPABASE_DB_PASSWORD'] ?? '',
    'sslmode' => 'prefer'  // require から prefer に変更
];

// PDO接続文字列の構築
$dsn = sprintf(
    "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s",
    $db_config['host'],
    $db_config['port'],
    $db_config['dbname'],
    $db_config['sslmode']
);

// PDO PostgreSQL ドライバーの確認
if (!extension_loaded('pdo_pgsql')) {
    if ($app_env === 'production') {
        die('データベース接続エラーが発生しました。');
    } else {
        echo "<h2>PDO PostgreSQL ドライバーエラー</h2>";
        echo "<p><strong>問題:</strong> PDO PostgreSQL ドライバーがインストールされていません。</p>";
        echo "<h3>解決方法:</h3>";
        echo "<ul>";
        echo "<li><strong>XAMPP/WAMP:</strong> php.ini で <code>extension=pdo_pgsql</code> のコメントアウトを解除</li>";
        echo "<li><strong>Windows:</strong> php_pgsql.dll と php_pdo_pgsql.dll を有効化</li>";
        echo "<li><strong>Linux:</strong> <code>sudo apt-get install php-pgsql</code> を実行</li>";
        echo "</ul>";
        echo "<h3>現在のPHP拡張:</h3>";
        echo "<ul>";
        foreach (PDO::getAvailableDrivers() as $driver) {
            echo "<li>$driver</li>";
        }
        echo "</ul>";
        die();
    }
}

try {
    // PDO接続の作成（タイムアウト設定を追加）
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30,  // 30秒のタイムアウト
    ]);
    
    // 文字エンコーディングの設定
    $pdo->exec("SET NAMES 'UTF8'");
    
} catch (PDOException $e) {
    // エラーログの記録
    error_log("Database connection failed: " . $e->getMessage());
    
    // 開発環境では詳細なエラー情報を表示
    if ($app_env === 'production') {
        die('データベース接続エラーが発生しました。');
    } else {
        echo "<h2>データベース接続エラー</h2>";
        echo "<p><strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<h3>接続設定:</h3>";
        echo "<ul>";
        echo "<li>Host: " . htmlspecialchars($db_config['host']) . "</li>";
        echo "<li>Port: " . htmlspecialchars($db_config['port']) . "</li>";
        echo "<li>Database: " . htmlspecialchars($db_config['dbname']) . "</li>";
        echo "<li>User: " . htmlspecialchars($db_config['user']) . "</li>";
        echo "<li>Password: " . (empty($db_config['password']) ? '未設定' : '設定済み') . "</li>";
        echo "</ul>";
        echo "<h3>環境変数の確認:</h3>";
        echo "<ul>";
        foreach (['SUPABASE_DB_HOST', 'SUPABASE_DB_PORT', 'SUPABASE_DB_NAME', 'SUPABASE_DB_USER', 'SUPABASE_DB_PASSWORD'] as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            echo "<li>$var: " . ($value ? '設定済み' : '未設定') . "</li>";
        }
        echo "</ul>";
        echo "<p><strong>解決方法:</strong> .envファイルを作成してデータベース接続情報を設定してください。</p>";
        die();
    }
}

/**
 * 検索用のSQLクエリを実行する関数
 * 
 * @param string $query 検索クエリ
 * @param int $limit 取得件数
 * @param int $offset オフセット
 * @return array 検索結果
 */
function searchBuildings($pdo, $query, $limit = 10, $offset = 0) {
    try {
        // 検索クエリの正規化（全角/半角スペースの統一）
        $normalizedQuery = preg_replace('/[\s\u{3000}]+/u', ' ', trim($query));
        
        if (empty($normalizedQuery)) {
            return ['results' => [], 'total' => 0];
        }
        
        // 検索用SQLクエリ
        $sql = "
            SELECT
                b.building_id,
                b.slug,
                b.title,
                b.\"titleEn\",
                b.\"buildingTypes\",
                b.\"buildingTypesEn\",
                b.location,
                b.\"locationEn_from_datasheetChunkEn\",
                b.\"completionYears\",
                b.lat,
                b.lng,
                COALESCE(
                    (
                        SELECT json_agg(
                            json_build_object(
                                'name_ja', ia.name_ja, 
                                'name_en', ia.name_en, 
                                'slug', ia.slug
                            ) ORDER BY ac.order_index
                        )
                        FROM building_architects ba
                        JOIN architect_compositions ac ON ba.architect_id = ac.architect_id
                        JOIN individual_architects ia ON ac.individual_architect_id = ia.individual_architect_id
                        WHERE ba.building_id = b.building_id
                    ),
                    '[]'::json
                ) as architects,
                ts_rank(
                    to_tsvector('simple',
                        COALESCE(b.title,'') || ' ' || 
                        COALESCE(b.\"titleEn\",'') || ' ' ||
                        COALESCE(b.\"buildingTypes\",'') || ' ' || 
                        COALESCE(b.\"buildingTypesEn\",'') || ' ' ||
                        COALESCE(b.location,'') || ' ' || 
                        COALESCE(b.\"locationEn_from_datasheetChunkEn\",'') || ' ' ||
                        COALESCE(
                            (
                                SELECT string_agg(ia.name_ja || ' ' || ia.name_en, ' ')
                                FROM building_architects ba
                                JOIN architect_compositions ac ON ba.architect_id = ac.architect_id
                                JOIN individual_architects ia ON ac.individual_architect_id = ia.individual_architect_id
                                WHERE ba.building_id = b.building_id
                            ),
                            ''
                        )
                    ), 
                    websearch_to_tsquery('simple', :query)
                ) as rank
            FROM buildings_table_2 b
            WHERE 
                to_tsvector('simple',
                    COALESCE(b.title,'') || ' ' || 
                    COALESCE(b.\"titleEn\",'') || ' ' ||
                    COALESCE(b.\"buildingTypes\",'') || ' ' || 
                    COALESCE(b.\"buildingTypesEn\",'') || ' ' ||
                    COALESCE(b.location,'') || ' ' || 
                    COALESCE(b.\"locationEn_from_datasheetChunkEn\",'') || ' ' ||
                    COALESCE(
                        (
                            SELECT string_agg(ia.name_ja || ' ' || ia.name_en, ' ')
                            FROM building_architects ba
                            JOIN architect_compositions ac ON ba.architect_id = ac.architect_id
                            JOIN individual_architects ia ON ac.individual_architect_id = ia.individual_architect_id
                            WHERE ba.building_id = b.building_id
                        ),
                        ''
                    )
                ) @@ websearch_to_tsquery('simple', :query)
            ORDER BY rank DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':query', $normalizedQuery, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        
        // 総件数を取得
        $countSql = "
            SELECT COUNT(*)
            FROM buildings_table_2 b
            WHERE 
                to_tsvector('simple',
                    COALESCE(b.title,'') || ' ' || 
                    COALESCE(b.\"titleEn\",'') || ' ' ||
                    COALESCE(b.\"buildingTypes\",'') || ' ' || 
                    COALESCE(b.\"buildingTypesEn\",'') || ' ' ||
                    COALESCE(b.location,'') || ' ' || 
                    COALESCE(b.\"locationEn_from_datasheetChunkEn\",'') || ' ' ||
                    COALESCE(
                        (
                            SELECT string_agg(ia.name_ja || ' ' || ia.name_en, ' ')
                            FROM building_architects ba
                            JOIN architect_compositions ac ON ba.architect_id = ac.architect_id
                            JOIN individual_architects ia ON ac.individual_architect_id = ia.individual_architect_id
                            WHERE ba.building_id = b.building_id
                        ),
                        ''
                    )
                ) @@ websearch_to_tsquery('simple', :query)
        ";
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':query', $normalizedQuery, PDO::PARAM_STR);
        $countStmt->execute();
        $total = $countStmt->fetchColumn();
        
        return [
            'results' => $results,
            'total' => (int)$total
        ];
        
    } catch (PDOException $e) {
        error_log("Search query failed: " . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * 建築家情報を取得する関数
 * 
 * @param PDO $pdo データベース接続
 * @param string $slug 建築家のスラッグ
 * @return array|null 建築家情報
 */
function getArchitectBySlug($pdo, $slug) {
    try {
        $sql = "
            SELECT 
                ia.*,
                (
                    SELECT json_agg(
                        json_build_object(
                            'building_id', b.building_id,
                            'slug', b.slug,
                            'title', b.title,
                            'titleEn', b.\"titleEn\",
                            'completionYears', b.\"completionYears\",
                            'location', b.location
                        ) ORDER BY b.\"completionYears\" DESC
                    )
                    FROM building_architects ba
                    JOIN buildings_table_2 b ON ba.building_id = b.building_id
                    WHERE ba.architect_id IN (
                        SELECT architect_id 
                        FROM architect_compositions 
                        WHERE individual_architect_id = ia.individual_architect_id
                    )
                ) as buildings
            FROM individual_architects ia
            WHERE ia.slug = :slug
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Architect query failed: " . $e->getMessage());
        return null;
    }
}

/**
 * 建築物情報を取得する関数
 * 
 * @param PDO $pdo データベース接続
 * @param string $slug 建築物のスラッグ
 * @return array|null 建築物情報
 */
function getBuildingBySlug($pdo, $slug) {
    try {
        $sql = "
            SELECT 
                b.*,
                (
                    SELECT json_agg(
                        json_build_object(
                            'name_ja', ia.name_ja,
                            'name_en', ia.name_en,
                            'slug', ia.slug
                        ) ORDER BY ac.order_index
                    )
                    FROM building_architects ba
                    JOIN architect_compositions ac ON ba.architect_id = ac.architect_id
                    JOIN individual_architects ia ON ac.individual_architect_id = ia.individual_architect_id
                    WHERE ba.building_id = b.building_id
                ) as architects
            FROM buildings_table_2 b
            WHERE b.slug = :slug
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Building query failed: " . $e->getMessage());
        return null;
    }
}

/**
 * エスケープ関数（XSS対策）
 * 
 * @param string $string エスケープする文字列
 * @return string エスケープされた文字列
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * JSONレスポンスを返す関数
 * 
 * @param array $data レスポンスデータ
 * @param int $statusCode HTTPステータスコード
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
