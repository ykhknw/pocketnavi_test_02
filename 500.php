<?php
/**
 * 500エラーページ
 * サーバーエラーが発生した場合の表示
 */

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サーバーエラー - PocketNavi</title>
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
                <h1>500</h1>
                <h2>サーバーエラーが発生しました</h2>
                <p>申し訳ございません。サーバー内部でエラーが発生しました。</p>
                <p>しばらく時間をおいてから再度お試しください。</p>
                
                <div class="error-actions">
                    <a href="/" class="btn btn-primary">トップページに戻る</a>
                    <a href="javascript:location.reload()" class="btn btn-secondary">ページを再読み込み</a>
                </div>
                
                <div class="error-info">
                    <p>問題が解決しない場合は、管理者にお問い合わせください。</p>
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
            color: #ea4335;
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
            margin-bottom: 16px;
        }
        
        .error-actions {
            margin: 40px 0;
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
        
        .error-info {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .error-info p {
            font-size: 0.9rem;
            color: #5f6368;
            margin: 0;
        }
    </style>
</body>
</html>
