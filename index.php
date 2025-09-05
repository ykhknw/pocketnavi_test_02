<?php
/**
 * トップ・検索ページ
 * 建築物検索システムのメインページ
 */

require_once 'db_connect_safe.php';

// 検索パラメータの取得
$query = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// 検索結果の初期化
$searchResults = [];
$totalResults = 0;
$totalPages = 0;

// 検索実行
if (!empty($query)) {
    $searchData = searchBuildings($pdo, $query, $limit, $offset);
    $searchResults = $searchData['results'];
    $totalResults = $searchData['total'];
    $totalPages = ceil($totalResults / $limit);
}

// AJAXリクエストの場合はJSONで返す
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    $response = [
        'results' => $searchResults,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_results' => $totalResults,
            'limit' => $limit
        ]
    ];
    
    jsonResponse($response);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($query) ? h($query) . ' - 検索結果' : 'PocketNavi - 建築物検索システム'; ?></title>
    <meta name="description" content="建築物検索システム。建築物名、建築家名、場所などで検索できます。">
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- ヘッダー -->
        <header class="header">
            <h1 class="logo">
                <a href="/">PocketNavi</a>
            </h1>
            <p class="subtitle">建築物検索システム</p>
        </header>

        <!-- 検索フォーム -->
        <div class="search-container">
            <form class="search-form" id="searchForm" method="GET" action="/">
                <div class="search-box">
                    <input 
                        type="text" 
                        id="searchInput" 
                        name="q"
                        class="search-input" 
                        placeholder="建築物名、建築家名、場所などを入力してください"
                        value="<?php echo h($query); ?>"
                        autocomplete="off"
                    >
                    <button type="submit" class="search-button" id="searchButton">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- 検索結果 -->
        <div class="results-container" id="resultsContainer">
            <?php if (!empty($query)): ?>
                <!-- 検索結果ヘッダー -->
                <div class="results-header">
                    <p class="results-info">
                        「<?php echo h($query); ?>」の検索結果: 
                        <strong><?php echo number_format($totalResults); ?></strong>件
                        <?php if ($totalPages > 1): ?>
                            （<?php echo $page; ?>/<?php echo $totalPages; ?>ページ）
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (!empty($searchResults)): ?>
                    <!-- 検索結果一覧 -->
                    <div class="results" id="results">
                        <?php foreach ($searchResults as $result): ?>
                            <div class="result-item">
                                <h2 class="result-title">
                                    <a href="/buildings/<?php echo h($result['slug']); ?>">
                                        <?php echo h($result['title']); ?>
                                    </a>
                                </h2>
                                
                                <?php if (!empty($result['titleEn'])): ?>
                                    <p class="result-title-en"><?php echo h($result['titleEn']); ?></p>
                                <?php endif; ?>
                                
                                <div class="result-meta">
                                    <?php if (!empty($result['location'])): ?>
                                        <div class="result-meta-item">
                                            <svg viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                            </svg>
                                            <?php echo h($result['location']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($result['completionYears'])): ?>
                                        <div class="result-meta-item">
                                            <svg viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                            </svg>
                                            <?php echo h($result['completionYears']); ?>年
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($result['buildingTypes'])): ?>
                                    <div class="result-types">
                                        <?php 
                                        $types = explode(',', $result['buildingTypes']);
                                        foreach ($types as $type): 
                                        ?>
                                            <span class="result-type"><?php echo h(trim($type)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['architects'])): ?>
                                    <div class="result-architects">
                                        <div class="result-architects-label">建築家:</div>
                                        <div class="architect-links">
                                            <?php foreach ($result['architects'] as $architect): ?>
                                                <a href="/architects/<?php echo h($architect['slug']); ?>" class="architect-link">
                                                    <?php echo h($architect['name_ja']); ?>
                                                    <?php if (!empty($architect['name_en'])): ?>
                                                        (<?php echo h($architect['name_en']); ?>)
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="result-rank">関連度: <?php echo number_format($result['rank'] * 100, 1); ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ページネーション -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination" id="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
                                    前へ
                                </a>
                            <?php endif; ?>
                            
                            <div class="pagination-info">
                                <?php echo $page; ?> / <?php echo $totalPages; ?> ページ
                            </div>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                                    次へ
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- 検索結果なし -->
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <h3>検索結果が見つかりませんでした</h3>
                        <p>別のキーワードで検索してみてください。</p>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- 初期状態（検索前） -->
                <div class="welcome-message">
                    <h2>建築物を検索しましょう</h2>
                    <p>建築物名、建築家名、場所などで検索できます。</p>
                    
                    <div class="search-examples">
                        <h3>検索例</h3>
                        <ul>
                            <li>「安藤忠雄」- 建築家名で検索</li>
                            <li>「教会」- 建築タイプで検索</li>
                            <li>「大阪」- 場所で検索</li>
                            <li>「光の教会」- 建築物名で検索</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
