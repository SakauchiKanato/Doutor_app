-- ドトール発注管理アプリ データベース初期化スクリプト
-- SQLite用

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 商品マスタテーブル
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    unit TEXT DEFAULT '個',
    safety_stock INTEGER DEFAULT 10,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 星ランク定義テーブル
CREATE TABLE IF NOT EXISTS star_definitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    star_level INTEGER NOT NULL CHECK(star_level >= 1 AND star_level <= 5),
    consumption_per_day INTEGER NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE(item_id, star_level)
);

-- イベントカレンダーテーブル
CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_date DATE NOT NULL,
    event_name TEXT NOT NULL,
    venue TEXT,
    recommended_star INTEGER CHECK(recommended_star >= 1 AND recommended_star <= 5),
    memo TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 発注予測記録テーブル
CREATE TABLE IF NOT EXISTS forecasts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    forecast_date DATE NOT NULL,
    target_date DATE NOT NULL,
    star_level INTEGER NOT NULL,
    predicted_consumption INTEGER NOT NULL,
    actual_consumption INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- 在庫記録テーブル
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    log_date DATE NOT NULL,
    quantity INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- デフォルトユーザー作成（パスワード: admin123）
-- password_hash('admin123', PASSWORD_DEFAULT) のハッシュ値
INSERT OR IGNORE INTO users (username, password) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- サンプル商品データ
INSERT OR IGNORE INTO items (id, name, unit, safety_stock) VALUES
(1, 'ブレンドコーヒー豆', 'kg', 5),
(2, '牛乳', 'L', 20),
(3, 'クロワッサン', '個', 30),
(4, 'サンドイッチ（ハム&チーズ）', '個', 20),
(5, 'ドーナツ', '個', 15),
(6, 'ミルクレープ', '個', 10),
(7, '紙コップ（M）', '個', 100),
(8, '紙コップ（L）', '個', 100),
(9, 'ストロー', '本', 200),
(10, 'ナプキン', '枚', 300);

-- サンプル星ランク定義（商品ID 1-5のみ）
-- ブレンドコーヒー豆
INSERT OR IGNORE INTO star_definitions (item_id, star_level, consumption_per_day) VALUES
(1, 1, 2),
(1, 2, 3),
(1, 3, 5),
(1, 4, 8),
(1, 5, 15);

-- 牛乳
INSERT OR IGNORE INTO star_definitions (item_id, star_level, consumption_per_day) VALUES
(2, 1, 10),
(2, 2, 15),
(2, 3, 25),
(2, 4, 40),
(2, 5, 70);

-- クロワッサン
INSERT OR IGNORE INTO star_definitions (item_id, star_level, consumption_per_day) VALUES
(3, 1, 20),
(3, 2, 30),
(3, 3, 50),
(3, 4, 80),
(3, 5, 150);

-- サンドイッチ
INSERT OR IGNORE INTO star_definitions (item_id, star_level, consumption_per_day) VALUES
(4, 1, 15),
(4, 2, 25),
(4, 3, 40),
(4, 4, 65),
(4, 5, 120);

-- ドーナツ
INSERT OR IGNORE INTO star_definitions (item_id, star_level, consumption_per_day) VALUES
(5, 1, 10),
(5, 2, 18),
(5, 3, 30),
(5, 4, 50),
(5, 5, 90);

-- サンプルイベントデータ
INSERT OR IGNORE INTO events (event_date, event_name, venue, recommended_star) VALUES
('2026-01-15', '幕張メッセ 展示会', '幕張メッセ', 4),
('2026-01-18', 'ZOZOマリン ライブイベント', 'ZOZOマリンスタジアム', 5),
('2026-01-25', '幕張メッセ コンサート', '幕張メッセ', 5);
