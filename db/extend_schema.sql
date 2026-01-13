-- 新機能用拡張テーブル
-- 1. 発注履歴・入荷予定テーブル
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE(item_id, delivery_date)
);

-- 2. 日ごとの星ランク設定保存テーブル
CREATE TABLE IF NOT EXISTS daily_stars (
    id SERIAL PRIMARY KEY,
    target_date DATE NOT NULL UNIQUE,
    star_level INTEGER NOT NULL CHECK(star_level >= 1 AND star_level <= 5),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
