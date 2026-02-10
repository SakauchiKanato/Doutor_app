#!/usr/bin/env python3
"""
ドトール発注管理アプリ - 機械学習予測スクリプト
過去のデータを元に、各商品の推奨発注量を予測します。
"""

import sys
import json
import psycopg2
from datetime import datetime, timedelta

# データベース接続設定（環境変数から取得することを推奨）
DB_CONFIG = {
    'host': 'localhost',
    'port': '5432',
    'database': 'knt416',
    'user': 'knt416',
    'password': 'nFb55bRP'
}

def get_db_connection():
    """PostgreSQLデータベースに接続"""
    return psycopg2.connect(**DB_CONFIG)

def fetch_historical_data(conn, days=30):
    """
    過去N日分の発注・消費データを取得
    """
    cursor = conn.cursor()
    
    # 過去のforecastsデータを取得
    query = """
        SELECT 
            f.item_id,
            i.name as item_name,
            f.target_date,
            f.star_level,
            f.predicted_consumption,
            f.actual_consumption,
            e.genre_id,
            eg.genre_name,
            e.expected_visitors
        FROM forecasts f
        JOIN items i ON f.item_id = i.id
        LEFT JOIN events e ON f.target_date = e.event_date
        LEFT JOIN event_genres eg ON e.genre_id = eg.id
        WHERE f.target_date >= %s
        AND f.actual_consumption IS NOT NULL
        ORDER BY f.target_date DESC
    """
    
    start_date = datetime.now() - timedelta(days=days)
    cursor.execute(query, (start_date,))
    
    columns = [desc[0] for desc in cursor.description]
    results = []
    for row in cursor.fetchall():
        results.append(dict(zip(columns, row)))
    
    cursor.close()
    return results

def calculate_adjusted_consumption(historical_data):
    """
    過去データから、星ランク別・ジャンル別の補正係数を計算
    """
    adjustments = {}
    
    for record in historical_data:
        item_id = record['item_id']
        star_level = record['star_level']
        genre_id = record['genre_id']
        predicted = record['predicted_consumption']
        actual = record['actual_consumption']
        
        if predicted and actual and predicted > 0:
            ratio = actual / predicted
            
            key = (item_id, star_level, genre_id)
            if key not in adjustments:
                adjustments[key] = []
            adjustments[key].append(ratio)
    
    # 平均を計算
    avg_adjustments = {}
    for key, ratios in adjustments.items():
        avg_adjustments[key] = sum(ratios) / len(ratios)
    
    return avg_adjustments

def predict_order_quantity(conn, target_date, star_levels):
    """
    指定日の発注推奨量を予測
    
    Args:
        conn: データベース接続
        target_date: 対象日（文字列 'YYYY-MM-DD'）
        star_levels: 日付ごとの星ランク辞書 {date: star_level}
    
    Returns:
        商品ごとの推奨発注量の辞書
    """
    cursor = conn.cursor()
    
    # 商品一覧と星ランク定義を取得
    query = """
        SELECT 
            i.id, i.name, i.safety_stock,
            s1.consumption_per_day as s1,
            s2.consumption_per_day as s2,
            s3.consumption_per_day as s3,
            s4.consumption_per_day as s4,
            s5.consumption_per_day as s5
        FROM items i
        LEFT JOIN star_definitions s1 ON i.id = s1.item_id AND s1.star_level = 1
        LEFT JOIN star_definitions s2 ON i.id = s2.item_id AND s2.star_level = 2
        LEFT JOIN star_definitions s3 ON i.id = s3.item_id AND s3.star_level = 3
        LEFT JOIN star_definitions s4 ON i.id = s4.item_id AND s4.star_level = 4
        LEFT JOIN star_definitions s5 ON i.id = s5.item_id AND s5.star_level = 5
    """
    cursor.execute(query)
    
    items = []
    for row in cursor.fetchall():
        items.append({
            'id': row[0],
            'name': row[1],
            'safety_stock': row[2],
            's1': row[3] or 0,
            's2': row[4] or 0,
            's3': row[5] or 0,
            's4': row[6] or 0,
            's5': row[7] or 0
        })
    
    # 過去データから補正係数を取得
    historical = fetch_historical_data(conn, days=60)
    adjustments = calculate_adjusted_consumption(historical)
    
    # 予測計算
    predictions = []
    for item in items:
        # 3日間の消費量を計算
        total_consumption = 0
        for date_str, star in star_levels.items():
            consumption_key = f's{star}'
            base_consumption = item.get(consumption_key, 0)
            
            # 補正係数の適用（あれば）
            adjusted_consumption = base_consumption
            for key, ratio in adjustments.items():
                if key[0] == item['id'] and key[1] == star:
                    adjusted_consumption = base_consumption * ratio
                    break
            
            total_consumption += adjusted_consumption
        
        # 推奨発注量 = 安全在庫 + 予測消費量
        # （実際の在庫はPHP側で入力されるため、ここでは含めない）
        recommended_qty = int(total_consumption)
        
        predictions.append({
            'item_id': item['id'],
            'item_name': item['name'],
            'predicted_consumption': int(total_consumption),
            'recommended_order_qty': recommended_qty,
            'confidence': len([r for r in historical if r['item_id'] == item['id']]) / max(len(historical), 1)
        })
    
    cursor.close()
    return predictions

def main():
    """
    メイン処理
    引数: JSON形式の入力 {"target_date": "2026-02-10", "star_levels": {"2026-02-10": 3, "2026-02-11": 4, "2026-02-12": 5}}
    """
    try:
        # コマンドライン引数から入力を取得
        if len(sys.argv) > 1:
            input_data = json.loads(sys.argv[1])
        else:
            # 標準入力から読み取る
            input_data = json.load(sys.stdin)
        
        target_date = input_data.get('target_date')
        star_levels = input_data.get('star_levels', {})
        
        # データベースに接続
        conn = get_db_connection()
        
        # 予測実行
        predictions = predict_order_quantity(conn, target_date, star_levels)
        
        # 結果をJSON形式で出力
        output = {
            'status': 'success',
            'predictions': predictions,
            'timestamp': datetime.now().isoformat()
        }
        
        print(json.dumps(output, ensure_ascii=False, indent=2))
        
        conn.close()
        
    except Exception as e:
        error_output = {
            'status': 'error',
            'message': str(e),
            'timestamp': datetime.now().isoformat()
        }
        print(json.dumps(error_output, ensure_ascii=False, indent=2))
        sys.exit(1)

if __name__ == '__main__':
    main()
