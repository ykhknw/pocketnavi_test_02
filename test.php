<?php
/**
 * テスト用のシンプルなPHPファイル
 * データベース接続なしで動作確認
 */

echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Error reporting is enabled.<br>";

// ファイルの存在確認
$files = ['index.php', 'db_connect.php', 'architect.php', 'building.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file not found<br>";
    }
}

// ディレクトリの確認
echo "Current directory: " . getcwd() . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
?>
