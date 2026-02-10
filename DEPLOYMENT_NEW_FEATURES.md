# 新機能デプロイメントガイド

## 概要

このドキュメントは、ゼミサーバーに新機能をデプロイするための詳細な手順を記載しています。

---

## 前提条件

- ゼミサーバーへのSSHアクセス権限
- PostgreSQLデータベースへのアクセス権限（ユーザー: knt416）
- Python 3.x がインストールされていること
- Gemini APIキー（無料取得可能）

---

## デプロイ手順

### 1. データベースバックアップ

**重要**: 必ず最初にバックアップを取得してください。

```bash
# PostgreSQLデータベースのバックアップ
pg_dump -U knt416 -d knt416 > ~/backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. データベースマイグレーション

新しいテーブルとカラムを追加します。

```bash
# 1. ユーザーロール機能の追加
psql -U knt416 -d knt416 -f db/add_user_roles.sql

# 2. 星ランク評価基準の変更履歴機能
psql -U knt416 -d knt416 -f db/add_star_criteria_history.sql

# 3. 日別星ランク設定テーブル
psql -U knt416 -d knt416 -f db/add_daily_stars.sql
```

**確認**:
```bash
psql -U knt416 -d knt416 -c "SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='role';"
```
「role」カラムが表示されればOKです。

### 3. ファイルのアップロード

以下のファイルをゼミサーバーにアップロードします。

**更新されたファイル**:
- `auth.php`
- `login.php`
- `items.php`
- `item_edit.php`
- `genre_manage.php`
- `event_edit.php`
- `includes/header.php`

**新規ファイル**:
- `star_criteria_manage.php`
- `ai_analysis.php`
- `includes/ai_helper.php`
- `ml/predict_inventory.py`
- `db/add_user_roles.sql`
- `db/add_star_criteria_history.sql`
- `db/add_daily_stars.sql`

### 4. ファイル権限の設定

```bash
# PHP ファイルに適切な権限を設定
chmod 644 *.php
chmod 644 includes/*.php
chmod 755 ml/  # ディレクトリ
chmod 755 ml/predict_inventory.py  # Python スクリプト
```

### 5. Python環境のセットアップ

```bash
# Python 3 がインストールされているか確認
python3 --version

# pipがインストールされているか確認
pip3 --version

# 必要なパッケージをインストール
pip3 install --user psycopg2-binary

# インストール確認
python3 -c "import psycopg2; print('OK')"
```

### 6. Gemini APIキーの設定

```bash
# APIキーを取得（無料）
# https://aistudio.google.com/app/apikey

# 環境変数を設定（永続化）
echo 'export GEMINI_API_KEY="あなたのAPIキー"' >> ~/.bashrc

# 設定を反映
source ~/.bashrc

# 確認
echo $GEMINI_API_KEY
```

または、`config.php` に直接記述する方法:
```php
// config.php の末尾に追加
putenv('GEMINI_API_KEY=あなたのAPIキー');
```

---

## 動作確認

### 1. ログイン確認

- URL: `https://your-server.ac.jp/~knt416/Doutor_app/`
- 管理者アカウント: `admin` / `admin123`
- 一般ユーザー: `staff` / `staff123`

### 2. 管理者機能の確認

管理者でログイン後、以下を確認:

#### ⭐ 評価基準管理
1. ナビゲーションメニューから「⭐ 評価基準」をクリック
2. 商品の星ランク別消費量が表示されることを確認
3. 値を変更して「保存」
4. 変更履歴に記録されることを確認

#### 📦 商品管理（CSV登録）
1. 「商品管理」→「📄 CSV一括登録」をクリック
2. テスト用CSVファイルを作成:
   ```csv
   商品名,単位,安全在庫数
   テスト商品A,個,10
   テスト商品B,kg,5
   ```
3. アップロードしてカラムマッピング画面が表示されることを確認
4. インポート実行後、商品一覧に追加されることを確認

### 3. ロール分離の確認

一般ユーザー（`staff`）でログイン後:
- メニューに管理者専用項目が表示されないことを確認
- URLを直接入力して `items.php` にアクセスしても拒否されることを確認

### 4. PythonMLスクリプトの確認

```bash
# スクリプトが実行できるか確認
cd /path/to/Doutor_app/ml
python3 predict_inventory.py '{"target_date": "2026-02-10", "star_levels": {"2026-02-10": 3}}'
```

正常に動作すれば、JSON形式の予測結果が出力されます。

### 5. Gemini AI分析の確認

1. 管理者でログイン
2. 「🤖 AI分析」メニューをクリック
3. APIキーの設定状態を確認（✅ または ❌）
4. 「分析を実行」ボタンをクリック
5. AIによる分析結果が表示されることを確認

---

## トラブルシューティング

### エラー: "requireAdmin() function not found"

**原因**: `auth.php` が正しくアップロードされていない

**解決策**:
```bash
# 最新の auth.php を再アップロード
# ファイルの内容を確認
head -20 auth.php
```

### エラー: "column 'role' does not exist"

**原因**: データベースマイグレーションが実行されていない

**解決策**:
```bash
psql -U knt416 -d knt416 -f db/add_user_roles.sql
```

### Python スクリプトエラー: "ModuleNotFoundError: No module named 'psycopg2'"

**原因**: psycopg2がインストールされていない

**解決策**:
```bash
pip3 install --user psycopg2-binary
```

### Gemini API エラー: "APIキーが設定されていません"

**原因**: 環境変数が設定されていない

**解決策**:
```bash
# 環境変数を確認
echo $GEMINI_API_KEY

# 未設定の場合
export GEMINI_API_KEY="your-api-key"
# または ~/.bashrc に追加して永続化
```

---

## ロールバック手順

万が一問題が発生した場合:

### データベースのロールバック

```bash
# バックアップから復元
psql -U knt416 -d knt416 < ~/backup_YYYYMMDD_HHMMSS.sql
```

### ファイルのロールバック

古いファイルを復元するか、以下のテーブルを削除:

```sql
-- ロール機能を無効化
ALTER TABLE users DROP COLUMN IF EXISTS role;

-- 履歴テーブルを削除
DROP TABLE IF EXISTS star_criteria_history CASCADE;
DROP TABLE IF EXISTS daily_stars CASCADE;
```

---

## デプロイチェックリスト

デプロイ前:
- [ ] データベースバックアップを取得
- [ ] 全ファイルをローカルでテスト済み
- [ ] Python環境の確認
- [ ] Gemini APIキーの取得

デプロイ中:
- [ ] データベースマイグレーション実行
- [ ] ファイルアップロード完了
- [ ] ファイル権限設定
- [ ] Python パッケージインストール
- [ ] 環境変数設定

デプロイ後:
- [ ] 管理者ログイン確認
- [ ] 一般ユーザーログイン確認
- [ ] 評価基準管理の動作確認
- [ ] CSV登録の動作確認
- [ ] ロール分離の確認
- [ ] （オプション）AI分析の動作確認

---

**作成日**: 2026年2月5日  
**最終更新**: 2026年2月5日  
**対象サーバー**: ゼミサーバー（knt416@your-server.ac.jp）
