import csv
import random
import datetime

# 設定
items = [
    "ブレンドコーヒー豆", "ホイップクリーム", "サンドイッチ用食パン", "ロースハム",
    "レタス", "オレンジジュース", "ガムシロップ", "テイクアウト用カップM",
    "チョコレートソース", "冷凍パスタ"
]
start_date = datetime.date(2026, 12, 12)
days = 60

# ファイル書き込み
with open('forecast_full.csv', 'w', newline='', encoding='utf-8') as f_forecast, \
     open('actual_full.csv', 'w', newline='', encoding='utf-8') as f_actual:
    
    writer_f = csv.writer(f_forecast)
    writer_a = csv.writer(f_actual)
    
    # ヘッダー
    writer_f.writerow(["日付", "商品名", "星ランク", "予測消費量", "実際の消費量", "残在庫", "発注量"])
    writer_a.writerow(["日付", "商品名", "消費量", "残在庫", "備考"])

    for i in range(days):
        current_date = start_date + datetime.timedelta(days=i)
        weekday = current_date.weekday() # 0:月 ~ 6:日
        
        # 曜日ベースのランク決定 (金土日は高い)
        if weekday >= 4:
            base_rank = random.choice([4, 5])
        else:
            base_rank = random.choice([1, 2, 3])
            
        # クリスマス補正
        is_xmas = (current_date.month == 12 and current_date.day in [24, 25])
        if is_xmas:
            base_rank = 5

        for item in items:
            # ベース消費量（商品によって変える）
            base_consumption = 10 if "コーヒー" in item else 5
            
            # ランクによる係数
            rank_factor = 1 + (base_rank * 0.2)
            if is_xmas: rank_factor *= 1.5
            
            # 予測と実際の生成
            forecast = round(base_consumption * rank_factor + random.uniform(-1, 1), 0)
            actual = round(forecast + random.uniform(-2, 2), 0) # 予測とのズレ
            if actual < 0: actual = 0
            
            stock = round(random.uniform(0, 5), 0)
            order = round(forecast * 1.2, 0) # 予測より少し多めに発注
            
            note = ""
            if is_xmas: note = "クリスマス"
            elif weekday >= 5: note = "週末"

            # 書き込み
            writer_f.writerow([current_date, item, base_rank, forecast, actual, stock, order])
            writer_a.writerow([current_date, item, actual, stock, note])

print("CSV生成完了")