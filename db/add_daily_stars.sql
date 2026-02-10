-- 日別星ランクテーブルの作成
-- order_calculator.phpで使用されているが、init_postgresql.sqlに含まれていない可能性があるため作成

CREATE TABLE IF NOT EXISTS daily_stars (
    id SERIAL PRIMARY KEY,
    target_date DATE NOT NULL UNIQUE,
    star_level INTEGER NOT NULL CHECK(star_level >= 1 AND star_level <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- インデックス
CREATE INDEX IF NOT EXISTS idx_daily_stars_date ON daily_stars(target_date);

-- ordersテーブルにユニーク制約を追加（既存データとの重複を防ぐ）
DO $$
BEGIN
    -- item_id + delivery_date のユニーク制約が存在しない場合のみ追加
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'orders_item_delivery_unique'
    ) THEN
        -- 既存の重複データを削除してから制約を追加
        DELETE FROM orders a USING orders b
        WHERE a.id > b.id 
        AND a.item_id = b.item_id 
        AND a.delivery_date = b.delivery_date;
        
        ALTER TABLE orders 
        ADD CONSTRAINT orders_item_delivery_unique 
        UNIQUE (item_id, delivery_date);
    END IF;
END $$;

-- inventory_logsテーブルにユニーク制約を追加
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'inventory_logs_item_date_unique'
    ) THEN
        -- 既存の重複データを削除してから制約を追加
        DELETE FROM inventory_logs a USING inventory_logs b
        WHERE a.id > b.id 
        AND a.item_id = b.item_id 
        AND a.log_date = b.log_date;
        
        ALTER TABLE inventory_logs 
        ADD CONSTRAINT inventory_logs_item_date_unique 
        UNIQUE (item_id, log_date);
    END IF;
END $$;

COMMENT ON TABLE daily_stars IS '日別の星ランク設定（全商品共通）';
