-- ユーザーロール機能の追加
-- PostgreSQL用マイグレーションスクリプト

-- 1. usersテーブルにroleカラムを追加
-- role: 'admin' (管理者) または 'user' (一般ユーザー)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user' 
CHECK (role IN ('admin', 'user'));

-- 2. 既存のadminユーザーをadminロールに設定
UPDATE users SET role = 'admin' WHERE username = 'admin';

-- 3. パフォーマンス向上のためのインデックス追加
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- 4. サンプルの一般ユーザーを作成（必要に応じて）
-- パスワード: staff123
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM users WHERE username = 'staff') THEN
        INSERT INTO users (username, password_hash, email, role) 
        VALUES ('staff', '$2y$12$0GJw1Ig5h48qdyJq9AKXCuZTWlUIXNdD9VoPBWU9DYjZ1CusT/kxK', 'staff@example.com', 'user');
    END IF;
END $$;

COMMENT ON COLUMN users.role IS 'ユーザーの権限: admin=管理者, user=一般ユーザー';
