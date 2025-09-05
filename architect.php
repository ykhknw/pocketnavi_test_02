<?php
/**
 * 建築家詳細ページ
 * /architects/{slug} のURLでアクセス
 */

require_once 'db_connect.php';

// スラッグの取得
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    include '404.php';
    exit;
}

// 建築家情報の取得
$architect = getArchitectBySlug($pdo, $slug);

if (!$architect) {
    http_response_code(404);
    include '404.php';
    exit;
}

// 建築物情報のデコード
$buildings = json_decode($architect['buildings'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($architect['name_ja']); ?><?php echo !empty($architect['name_en']) ? ' (' . h($architect['name_en']) . ')' : ''; ?> - PocketNavi</title>
    <meta name="description" content="<?php echo h($architect['name_ja']); ?>の建築作品一覧。<?php echo count($buildings); ?>件の建築物を掲載。">
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
            <nav class="breadcrumb">
                <a href="/">ホーム</a> &gt; 
                <a href="/architects">建築家</a> &gt; 
                <span><?php echo h($architect['name_ja']); ?></span>
            </nav>
        </header>

        <!-- 建築家情報 -->
        <div class="architect-profile">
            <div class="architect-header">
                <h1 class="architect-name">
                    <?php echo h($architect['name_ja']); ?>
                    <?php if (!empty($architect['name_en'])): ?>
                        <span class="architect-name-en"><?php echo h($architect['name_en']); ?></span>
                    <?php endif; ?>
                </h1>
                
                <?php if (!empty($architect['birth_year']) || !empty($architect['death_year'])): ?>
                    <div class="architect-lifespan">
                        <?php if (!empty($architect['birth_year'])): ?>
                            <?php echo h($architect['birth_year']); ?>年生まれ
                        <?php endif; ?>
                        <?php if (!empty($architect['death_year'])): ?>
                            - <?php echo h($architect['death_year']); ?>年没
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($architect['biography'])): ?>
                <div class="architect-biography">
                    <h2>略歴</h2>
                    <div class="biography-content">
                        <?php echo nl2br(h($architect['biography'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($architect['awards'])): ?>
                <div class="architect-awards">
                    <h2>受賞歴</h2>
                    <div class="awards-content">
                        <?php echo nl2br(h($architect['awards'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 建築作品一覧 -->
        <div class="buildings-section">
            <h2>建築作品</h2>
            <p class="buildings-count"><?php echo count($buildings); ?>件の建築物</p>
            
            <?php if (!empty($buildings)): ?>
                <div class="buildings-grid">
                    <?php foreach ($buildings as $building): ?>
                        <div class="building-card">
                            <h3 class="building-title">
                                <a href="/buildings/<?php echo h($building['slug']); ?>">
                                    <?php echo h($building['title']); ?>
                                </a>
                            </h3>
                            
                            <?php if (!empty($building['titleEn'])): ?>
                                <p class="building-title-en"><?php echo h($building['titleEn']); ?></p>
                            <?php endif; ?>
                            
                            <div class="building-meta">
                                <?php if (!empty($building['completionYears'])): ?>
                                    <div class="building-year">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                        </svg>
                                        <?php echo h($building['completionYears']); ?>年
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($building['location'])): ?>
                                    <div class="building-location">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                        </svg>
                                        <?php echo h($building['location']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>建築作品の情報がありません。</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 関連リンク -->
        <div class="related-links">
            <h2>関連リンク</h2>
            <ul>
                <li><a href="/">トップページに戻る</a></li>
                <li><a href="/?q=<?php echo urlencode($architect['name_ja']); ?>"><?php echo h($architect['name_ja']); ?>で検索</a></li>
            </ul>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
