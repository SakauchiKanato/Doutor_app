-- ドトール発注管理アプリ データベース初期化スクリプト
-- PostgreSQL用（既存データベース対応版）

-- 商品マスタテーブル
CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) DEFAULT '個',
    safety_stock INTEGER DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 星ランク定義テーブル
CREATE TABLE IF NOT EXISTS star_definitions (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL,
    star_level INTEGER NOT NULL CHECK(star_level >= 1 AND star_level <= 5),
    consumption_per_day INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE(item_id, star_level)
);

-- イベントカレンダーテーブル
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    event_date DATE NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    venue VARCHAR(255),
    recommended_star INTEGER CHECK(recommended_star >= 1 AND recommended_star <= 5),
    memo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 発注予測記録テーブル
CREATE TABLE IF NOT EXISTS forecasts (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL,
    forecast_date DATE NOT NULL,
    target_date DATE NOT NULL,
    star_level INTEGER NOT NULL,
    predicted_consumption INTEGER NOT NULL,
    actual_consumption INTEGER,
    remaining_stock INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE(item_id, target_date)
);

-- 在庫記録テーブル
CREATE TABLE IF NOT EXISTS inventory_logs (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL,
    log_date DATE NOT NULL,
    quantity INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- デフォルトユーザー作成（パスワード: admin123）
-- 既存のusersテーブルを使用（email, password_hashカラムがある場合）
-- usernameがユニークでない場合があるため、存在チェックしてから挿入
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin') THEN
        INSERT INTO users (username, password_hash, email) 
        VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');
    END IF;
END $$;

-- サンプル商品データ（重複を避けるため、存在チェック）
DO $$
BEGIN
    -- ブレンドコーヒー豆
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'ブレンドコーヒー豆') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('ブレンドコーヒー豆', 'kg', 5);
    END IF;
    
    -- 牛乳
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = '牛乳') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('牛乳', 'L', 20);
    END IF;
    
    -- クロワッサン
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'クロワッサン') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('クロワッサン', '個', 30);
    END IF;
    
    -- サンドイッチ
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'サンドイッチ（ハム&チーズ）') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('サンドイッチ（ハム&チーズ）', '個', 20);
    END IF;
    
    -- ドーナツ
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'ドーナツ') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('ドーナツ', '個', 15);
    END IF;
    
    -- ミルクレープ
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'ミルクレープ') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('ミルクレープ', '個', 10);
    END IF;
    
    -- 紙コップ（M）
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = '紙コップ（M）') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('紙コップ（M）', '個', 100);
    END IF;
    
    -- 紙コップ（L）
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = '紙コップ（L）') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('紙コップ（L）', '個', 100);
    END IF;
    
    -- ストロー
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'ストロー') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('ストロー', '本', 200);
    END IF;
    
    -- ナプキン
    IF NOT EXISTS (SELECT 1 FROM items WHERE name = 'ナプキン') THEN
        INSERT INTO items (name, unit, safety_stock) VALUES ('ナプキン', '枚', 300);
    END IF;
END $$;

-- 星ランク定義を挿入（UNIQUE制約があるため、ON CONFLICTが使える）
INSERT INTO star_definitions (item_id, star_level, consumption_per_day) 
SELECT i.id, s.star_level, s.consumption_per_day
FROM items i
CROSS JOIN (VALUES 
    ('ブレンドコーヒー豆', 1, 2),
    ('ブレンドコーヒー豆', 2, 3),
    ('ブレンドコーヒー豆', 3, 5),
    ('ブレンドコーヒー豆', 4, 8),
    ('ブレンドコーヒー豆', 5, 15),
    ('牛乳', 1, 10),
    ('牛乳', 2, 15),
    ('牛乳', 3, 25),
    ('牛乳', 4, 40),
    ('牛乳', 5, 70),
    ('クロワッサン', 1, 20),
    ('クロワッサン', 2, 30),
    ('クロワッサン', 3, 50),
    ('クロワッサン', 4, 80),
    ('クロワッサン', 5, 150),
    ('サンドイッチ（ハム&チーズ）', 1, 15),
    ('サンドイッチ（ハム&チーズ）', 2, 25),
    ('サンドイッチ（ハム&チーズ）', 3, 40),
    ('サンドイッチ（ハム&チーズ）', 4, 65),
    ('サンドイッチ（ハム&チーズ）', 5, 120),
    ('ドーナツ', 1, 10),
    ('ドーナツ', 2, 18),
    ('ドーナツ', 3, 30),
    ('ドーナツ', 4, 50),
    ('ドーナツ', 5, 90)
) AS s(item_name, star_level, consumption_per_day)
WHERE i.name = s.item_name
ON CONFLICT (item_id, star_level) DO NOTHING;

-- サンプルイベントデータ
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM events WHERE event_date = '2026-01-15' AND event_name = '幕張メッセ 展示会') THEN
        INSERT INTO events (event_date, event_name, venue, recommended_star) 
        VALUES ('2026-01-15', '幕張メッセ 展示会', '幕張メッセ', 4);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM events WHERE event_date = '2026-01-18' AND event_name = 'ZOZOマリン ライブイベント') THEN
        INSERT INTO events (event_date, event_name, venue, recommended_star) 
        VALUES ('2026-01-18', 'ZOZOマリン ライブイベント', 'ZOZOマリンスタジアム', 5);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM events WHERE event_date = '2026-01-25' AND event_name = '幕張メッセ コンサート') THEN
        INSERT INTO events (event_date, event_name, venue, recommended_star) 
        VALUES ('2026-01-25', '幕張メッセ コンサート', '幕張メッセ', 5);
    END IF;
END $$;
