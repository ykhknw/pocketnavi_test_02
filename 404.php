<?php
/**
 * 404エラーページ
 * ページが見つからない場合の表示
 */

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ページが見つかりません - PocketNavi</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="logo">
                <a href="/">PocketNavi</a>
            </h1>
        </header>

        <div class="error-page">
            <div class="error-content">
                <h1>404</h1>
                <h2>ページが見つかりません</h2>
                <p>お探しのページは存在しないか、移動された可能性があります。</p>
                
                <div class="error-actions">
                    <a href="/" class="btn btn-primary">トップページに戻る</a>
                    <a href="javascript:history.back()" class="btn btn-secondary">前のページに戻る</a>
                </div>
                
                <div class="error-suggestions">
                    <h3>お探しのものはこちらかもしれません：</h3>
                    <ul>
                        <li><a href="/">建築物検索</a></li>
                        <li><a href="/?q=安藤忠雄">安藤忠雄の作品</a></li>
                        <li><a href="/?q=教会">教会建築</a></li>
                        <li><a href="/?q=大阪">大阪の建築物</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
        .error-page {
            text-align: center;
            padding: 60px 20px;
        }
        
        .error-content h1 {
            font-size: 6rem;
            font-weight: 300;
            color: #1a73e8;
            margin-bottom: 20px;
        }
        
        .error-content h2 {
            font-size: 2rem;
            color: #202124;
            margin-bottom: 16px;
        }
        
        .error-content p {
            font-size: 1.1rem;
            color: #5f6368;
            margin-bottom: 40px;
        }
        
        .error-actions {
            margin-bottom: 40px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 8px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #1a73e8;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1557b0;
        }
        
        .btn-secondary {
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
        }
        
        .error-suggestions {
            text-align: left;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .error-suggestions h3 {
            font-size: 1.1rem;
            color: #202124;
            margin-bottom: 12px;
        }
        
        .error-suggestions ul {
            list-style: none;
            padding: 0;
        }
        
        .error-suggestions li {
            margin-bottom: 8px;
        }
        
        .error-suggestions a {
            color: #1a73e8;
            text-decoration: none;
        }
        
        .error-suggestions a:hover {
            text-decoration: underline;
        }
    </style>
</body>
</html>
