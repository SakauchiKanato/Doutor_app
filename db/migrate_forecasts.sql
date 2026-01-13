-- 発注予測テーブルにユニーク制約を追加するスクリプト
-- 重複保存を防ぎ、ON CONFLICT 構文を動作させるために必要です

-- 1. 重複データがある場合、最新のものだけ残して削除
DELETE FROM forecasts a USING forecasts b
WHERE a.id < b.id 
  AND a.item_id = b.item_id 
  AND a.target_date = b.target_date;

-- 2. ユニーク制約を追加
ALTER TABLE forecasts ADD CONSTRAINT unique_item_target UNIQUE (item_id, target_date);
