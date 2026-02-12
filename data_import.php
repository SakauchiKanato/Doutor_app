<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = 'データインポート';

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📥 データインポート</h2>
    </div>
    
    <div class="alert alert-info">
        <strong>💡 過去データのインポートについて:</strong><br>
        過去の発注データや実績データをCSVファイルから一括登録できます。<br>
        機械学習・AI分析の精度向上に役立ちます。
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
        <!-- 発注データインポート -->
        <div class="card" style="background: #f0f8ff;">
            <div class="card-header">
                <h3>📊 発注データ</h3>
            </div>
            <p>過去の発注記録（日付、商品、星ランク、予測消費量、実績など）をインポートします。</p>
            
            <div style="margin-top: 1rem;">
                <strong>含まれるデータ:</strong>
                <ul>
                    <li>対象日</li>
                    <li>商品名</li>
                    <li>星ランク</li>
                    <li>予測消費量</li>
                    <li>実際の消費量（オプション）</li>
                    <li>残在庫（オプション）</li>
                    <li>発注量（オプション）</li>
                </ul>
            </div>
            
            <a href="forecast_import.php" class="btn btn-primary" style="margin-top: 1rem;">
                📤 発注データをインポート
            </a>
        </div>
        
        <!-- 実績データインポート -->
        <div class="card" style="background: #fff5e6;">
            <div class="card-header">
                <h3>✅ 実績データ</h3>
            </div>
            <p>過去の実績消費データ（日付、商品、消費量、在庫など）をインポートします。</p>
            
            <div style="margin-top: 1rem;">
                <strong>含まれるデータ:</strong>
                <ul>
                    <li>記録日</li>
                    <li>商品名</li>
                    <li>消費量</li>
                    <li>残在庫（オプション）</li>
                    <li>備考（オプション）</li>
                </ul>
            </div>
            
            <a href="actual_import.php" class="btn btn-primary" style="margin-top: 1rem;">
                📤 実績データをインポート
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>💡 インポートのポイント</h3>
    </div>
    <ul>
        <li><strong>柔軟なCSVフォーマット:</strong> 商品登録と同様に、どんなCSVフォーマットでも対応できます。</li>
        <li><strong>自動カラム検出:</strong> 「日付」「商品名」などのキーワードを自動検出してマッピングします。</li>
        <li><strong>商品名の自動変換:</strong> CSV内の商品名を自動的にitem_idに変換します。</li>
        <li><strong>データ検証:</strong> 日付形式や数値範囲を自動チェックします。</li>
        <li><strong>重複データの処理:</strong> 既存データとの重複を検出し、スキップまたは更新を選択できます。</li>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>
