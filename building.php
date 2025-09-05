<?php
/**
 * 建築物詳細ページ
 * /buildings/{slug} のURLでアクセス
 */

require_once 'db_connect_rest.php';

// スラッグの取得
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    include '404.php';
    exit;
}

// 建築物情報の取得
$building = getBuildingBySlug($pdo, $slug);

if (!$building) {
    http_response_code(404);
    include '404.php';
    exit;
}

// 建築家情報のデコード
$architects = json_decode($building['architects'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($building['title']); ?><?php echo !empty($building['titleEn']) ? ' (' . h($building['titleEn']) . ')' : ''; ?> - PocketNavi</title>
    <meta name="description" content="<?php echo h($building['title']); ?>の詳細情報。建築家、完成年、場所などの情報を掲載。">
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
                <a href="/buildings">建築物</a> &gt; 
                <span><?php echo h($building['title']); ?></span>
            </nav>
        </header>

        <!-- 建築物詳細 -->
        <div class="building-detail">
            <div class="building-header">
                <h1 class="building-title">
                    <?php echo h($building['title']); ?>
                    <?php if (!empty($building['titleEn'])): ?>
                        <span class="building-title-en"><?php echo h($building['titleEn']); ?></span>
                    <?php endif; ?>
                </h1>
            </div>

            <!-- 基本情報 -->
            <div class="building-info">
                <div class="info-grid">
                    <?php if (!empty($building['completionYears'])): ?>
                        <div class="info-item">
                            <h3>完成年</h3>
                            <p><?php echo h($building['completionYears']); ?>年</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($building['location'])): ?>
                        <div class="info-item">
                            <h3>所在地</h3>
                            <p><?php echo h($building['location']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($building['locationEn_from_datasheetChunkEn'])): ?>
                        <div class="info-item">
                            <h3>所在地（英語）</h3>
                            <p><?php echo h($building['locationEn_from_datasheetChunkEn']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($building['buildingTypes'])): ?>
                        <div class="info-item">
                            <h3>建築タイプ</h3>
                            <div class="building-types">
                                <?php 
                                $types = explode(',', $building['buildingTypes']);
                                foreach ($types as $type): 
                                ?>
                                    <span class="building-type"><?php echo h(trim($type)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($building['buildingTypesEn'])): ?>
                        <div class="info-item">
                            <h3>建築タイプ（英語）</h3>
                            <div class="building-types">
                                <?php 
                                $typesEn = explode(',', $building['buildingTypesEn']);
                                foreach ($typesEn as $type): 
                                ?>
                                    <span class="building-type"><?php echo h(trim($type)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($building['lat']) && !empty($building['lng'])): ?>
                        <div class="info-item">
                            <h3>座標</h3>
                            <p>
                                緯度: <?php echo number_format($building['lat'], 6); ?><br>
                                経度: <?php echo number_format($building['lng'], 6); ?>
                            </p>
                            <div class="map-link">
                                <a href="https://www.google.com/maps?q=<?php echo $building['lat']; ?>,<?php echo $building['lng']; ?>" 
                                   target="_blank" rel="noopener noreferrer">
                                    Googleマップで表示
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 建築家情報 -->
            <?php if (!empty($architects)): ?>
                <div class="architects-section">
                    <h2>建築家</h2>
                    <div class="architects-list">
                        <?php foreach ($architects as $architect): ?>
                            <div class="architect-item">
                                <h3>
                                    <a href="/architects/<?php echo h($architect['slug']); ?>">
                                        <?php echo h($architect['name_ja']); ?>
                                    </a>
                                </h3>
                                <?php if (!empty($architect['name_en'])): ?>
                                    <p class="architect-name-en"><?php echo h($architect['name_en']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 詳細情報 -->
            <?php if (!empty($building['description'])): ?>
                <div class="building-description">
                    <h2>詳細情報</h2>
                    <div class="description-content">
                        <?php echo nl2br(h($building['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 建築史情報 -->
            <?php if (!empty($building['history'])): ?>
                <div class="building-history">
                    <h2>建築史</h2>
                    <div class="history-content">
                        <?php echo nl2br(h($building['history'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 技術情報 -->
            <?php if (!empty($building['technical_info'])): ?>
                <div class="building-technical">
                    <h2>技術情報</h2>
                    <div class="technical-content">
                        <?php echo nl2br(h($building['technical_info'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 関連リンク -->
        <div class="related-links">
            <h2>関連リンク</h2>
            <ul>
                <li><a href="/">トップページに戻る</a></li>
                <?php if (!empty($architects)): ?>
                    <?php foreach ($architects as $architect): ?>
                        <li>
                            <a href="/architects/<?php echo h($architect['slug']); ?>">
                                <?php echo h($architect['name_ja']); ?>の他の作品
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($building['location'])): ?>
                    <li><a href="/?q=<?php echo urlencode($building['location']); ?>"><?php echo h($building['location']); ?>の建築物</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
