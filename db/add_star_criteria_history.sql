-- 星ランク定義の変更履歴を記録するテーブル
-- PostgreSQL用マイグレーションスクリプト

-- 1. 星ランク定義の変更履歴テーブル
CREATE TABLE IF NOT EXISTS star_criteria_history (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL,
    star_level INTEGER NOT NULL CHECK(star_level >= 1 AND star_level <= 5),
    old_consumption INTEGER,
    new_consumption INTEGER NOT NULL,
    changed_by INTEGER, -- 変更したユーザーID
    change_reason TEXT, -- 変更理由（オプション）
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. インデックスの作成
CREATE INDEX IF NOT EXISTS idx_star_history_item ON star_criteria_history(item_id);
CREATE INDEX IF NOT EXISTS idx_star_history_date ON star_criteria_history(changed_at);

-- 3. star_definitionsテーブルにupdated_byカラムを追加（誰が最後に更新したか）
ALTER TABLE star_definitions 
ADD COLUMN IF NOT EXISTS updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL;

COMMENT ON TABLE star_criteria_history IS '星ランク別消費量定義の変更履歴を記録';
COMMENT ON COLUMN star_criteria_history.old_consumption IS '変更前の1日あたり消費量';
COMMENT ON COLUMN star_criteria_history.new_consumption IS '変更後の1日あたり消費量';
