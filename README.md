# PocketNavi 建築物検索システム

PHP + PostgreSQL を使用した建築物検索システムです。フレンドリーURLとレスポンシブデザインを特徴とします。

## 機能概要

- **全文検索**: PostgreSQL の全文検索機能を使用した高性能な検索
- **複数テーブル検索**: 建築物、建築家、場所などの複数フィールドを横断検索
- **関連度スコア**: ts_rank による検索結果の関連度スコアリング
- **ページネーション**: 大量データに対応したページネーション機能
- **フレンドリーURL**: .htaccess による美しいURL構造
- **レスポンシブデザイン**: Google検索風のシンプルで使いやすいUI

## 技術スタック

- **バックエンド**: PHP 8.0+
- **データベース**: PostgreSQL (Supabase)
- **フロントエンド**: HTML5, CSS3, JavaScript (Vanilla)
- **Webサーバー**: Apache (mod_rewrite対応)
- **検索エンジン**: PostgreSQL Full-Text Search

## プロジェクト構造

```
pocketnavi_test_02/
├── index.php                     # トップ・検索ページ
├── architect.php                 # 建築家詳細ページ
├── building.php                  # 建築物詳細ページ
├── db_connect.php                # DB接続共通
├── 404.php                       # 404エラーページ
├── 500.php                       # 500エラーページ
├── .htaccess                     # URLリライト設定
├── css/
│   └── style.css                 # スタイルシート
├── js/
│   └── main.js                   # JavaScript
├── images/                       # 画像ファイル（予定）
└── README.md                     # このファイル
```

## セットアップ

### 1. 前提条件

- PHP 8.0 以上
- Apache Webサーバー (mod_rewrite対応)
- PostgreSQL データベース (Supabase推奨)
- PDO PostgreSQL 拡張

### 2. 環境変数の設定

`.env` ファイルを作成し、データベース接続情報を設定：

```env
SUPABASE_DB_HOST=your-supabase-host
SUPABASE_DB_PORT=5432
SUPABASE_DB_NAME=postgres
SUPABASE_DB_USER=postgres
SUPABASE_DB_PASSWORD=your-password
APP_ENV=production
```

### 3. データベースの準備

Supabase プロジェクトで以下のテーブルが存在することを確認：

- `buildings_table_2`: 建築物情報
- `individual_architects`: 建築家情報
- `architect_compositions`: 建築家の構成情報
- `building_architects`: 建築物と建築家の関連

### 4. 検索関数の作成

Supabase SQL Editor で以下の関数を実行：

```sql
-- 検索用のRPC関数を作成
CREATE OR REPLACE FUNCTION search_buildings(
  search_query text,
  limit_param int DEFAULT 10,
  offset_param int DEFAULT 0
)
RETURNS TABLE(
  building_id int,
  slug text,
  title text,
  "titleEn" text,
  "buildingTypes" text,
  "buildingTypesEn" text,
  location text,
  "locationEn_from_datasheetChunkEn" text,
  "completionYears" text,
  lat float8,
  lng float8,
  architects json,
  rank float4
) 
LANGUAGE plpgsql
AS $$
-- 関数の実装は db_connect.php を参照
$$;
```

### 5. Webサーバーの設定

Apache の設定で mod_rewrite が有効になっていることを確認：

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### 6. ファイルのアップロード

すべてのファイルをWebサーバーのドキュメントルートにアップロード。

## URL構造

### フレンドリーURL

- **トップページ**: `/`
- **検索結果**: `/?q=検索クエリ&page=1`
- **建築家詳細**: `/architects/{slug}`
- **建築物詳細**: `/buildings/{slug}`

### 例

- `/architects/tadao-ando/` → `architect.php?slug=tadao-ando`
- `/buildings/church-of-light/` → `building.php?slug=church-of-light`

### レスポンス例

```json
{
  "results": [
    {
      "building_id": 101,
      "slug": "osaka-prefectural-gymnasium",
      "title": "大阪府立体育会館",
      "titleEn": "Osaka Prefectural Gymnasium",
      "buildingTypes": "体育館, 公共建築",
      "buildingTypesEn": "Gymnasium, Public Building",
      "location": "大阪府大阪市",
      "locationEn_from_datasheetChunkEn": "Osaka, Japan",
      "completionYears": "2012",
      "lat": 34.401489,
      "lng": 132.454407,
      "architects": [
        {
          "name_ja": "安藤忠雄",
          "name_en": "Tadao Ando",
          "slug": "tadao-ando"
        }
      ],
      "rank": 0.123
    }
  ],
  "pagination": {
    "limit": 10,
    "offset": 0,
    "total": 123
  }
}
```

## データベーススキーマ

### 主要テーブル

- `buildings_table_2`: 建築物情報
- `individual_architects`: 建築家情報
- `architect_compositions`: 建築家の構成情報
- `building_architects`: 建築物と建築家の関連

### 検索対象フィールド

- `title`, `titleEn`: 建築物名
- `buildingTypes`, `buildingTypesEn`: 建築タイプ
- `location`, `locationEn_from_datasheetChunkEn`: 場所
- `name_ja`, `name_en`: 建築家名

## 検索機能の詳細

### 全文検索の仕組み

1. **クエリ正規化**: 全角/半角スペースの統一
2. **tsquery変換**: `websearch_to_tsquery` を使用したクエリ変換
3. **tsvector生成**: 複数フィールドの結合による検索ベクトル作成
4. **関連度計算**: `ts_rank` によるスコア計算
5. **結果ソート**: 関連度の高い順にソート

### パフォーマンス最適化

- GINインデックスによる高速全文検索
- 建築家関連テーブルの適切なインデックス
- ページネーションによる効率的なデータ取得

## フロントエンド機能

### 主要機能

- **リアルタイム検索**: 入力に応じた検索実行
- **ページネーション**: 前後ページへの移動
- **検索履歴**: ローカルストレージによる履歴保存
- **レスポンシブデザイン**: モバイル対応
- **ダークモード対応**: システム設定に応じた自動切り替え

### キーボードショートカット

- `Ctrl/Cmd + K`: 検索ボックスにフォーカス
- `Escape`: 検索ボックスからフォーカスを外す

## 開発・デバッグ

### ローカル開発

```bash
# ローカル環境の開始
supabase start

# Edge Function のローカル実行
supabase functions serve

# データベースのリセット
supabase db reset
```

### ログの確認

```bash
# Edge Function のログ
supabase functions logs search

# データベースのログ
supabase db logs
```

## デプロイメント

### 本番環境へのデプロイ

```bash
# Edge Function のデプロイ
supabase functions deploy search

# データベースマイグレーション
supabase db push
```

### 環境変数

以下の環境変数がSupabase Edge Functionsで自動的に設定されます：

- `SUPABASE_URL`: Supabase プロジェクトのURL
- `SUPABASE_ANON_KEY`: 匿名アクセス用のAPIキー

## トラブルシューティング

### よくある問題

1. **検索結果が返らない**
   - データベースにデータが存在するか確認
   - インデックスが正しく作成されているか確認

2. **パフォーマンスが遅い**
   - インデックスの再作成
   - クエリの最適化

3. **CORS エラー**
   - Edge Function のCORS設定を確認

## ライセンス

MIT License

## 貢献

プルリクエストやイシューの報告を歓迎します。

## 更新履歴

- v1.0.0: 初期リリース
  - 基本的な検索機能
  - フロントエンド実装
  - ページネーション機能
