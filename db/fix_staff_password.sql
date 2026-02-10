-- 既存のスタッフアカウントのパスワードを更新
-- パスワード: staff123
-- このスクリプトは既存データベースでスタッフアカウントのパスワードが正しくない場合に使用

UPDATE users 
SET password_hash = '$2y$12$0GJw1Ig5h48qdyJq9AKXCuZTWlUIXNdD9VoPBWU9DYjZ1CusT/kxK'
WHERE username = 'staff';

-- 確認クエリ（実行後、1行更新されたことを確認）
SELECT username, email, role, 
       CASE WHEN password_hash = '$2y$12$0GJw1Ig5h48qdyJq9AKXCuZTWlUIXNdD9VoPBWU9DYjZ1CusT/kxK' 
            THEN '✅ 正しいパスワードハッシュ' 
            ELSE '❌ パスワードハッシュが異なります' 
       END as password_status
FROM users 
WHERE username = 'staff';
