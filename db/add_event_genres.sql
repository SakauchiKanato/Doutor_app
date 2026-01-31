-- イベントジャンル・来場予想数機能の追加
-- PostgreSQL用マイグレーションスクリプト

-- 1. イベントジャンルマスタテーブルの作成
CREATE TABLE IF NOT EXISTS event_genres (
    id SERIAL PRIMARY KEY,
    genre_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. eventsテーブルへのカラム追加
-- genre_id: イベントジャンルへの外部キー（NULL許可、後から追加可能）
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS genre_id INTEGER REFERENCES event_genres(id) ON DELETE SET NULL;

-- expected_visitors: 来場予想数（整数、NULL許可）
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS expected_visitors INTEGER;

-- 3. 初期ジャンルデータの登録
INSERT INTO event_genres (genre_name, description) 
VALUES 
    ('音楽イベント', '音楽系のイベント。昼間に売り上げが伸びる傾向がある。'),
    ('ビジネスイベント', 'ビジネス系のイベント。朝に売り上げが伸びる傾向がある。')
ON CONFLICT (genre_name) DO NOTHING;

-- 4. インデックスの作成（パフォーマンス向上のため）
CREATE INDEX IF NOT EXISTS idx_events_genre_id ON events(genre_id);
CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date);
