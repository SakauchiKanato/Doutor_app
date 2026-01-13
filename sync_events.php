<?php
require_once 'auth.php';
require_once 'config.php';

// エラー表示を有効にする（デバッグ時切り替え）
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = getDB();
$month_param = $_GET['month'] ?? date('Ym');
$year = substr($month_param, 0, 4);
$url = "https://www.m-messe.co.jp/event/print?month=" . $month_param;

$count = 0;

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // gzip等の自動展開
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($html === false) {
        throw new Exception("公式サイトからのデータ取得に失敗しました。cURLエラー: " . $curl_error);
    }
    if ($http_code !== 200) {
        throw new Exception("公式サイトからエラーが返されました。HTTPコード: " . $http_code);
    }

    // 文字コードの強制変換（DOMDocumentの文字化け対策）
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // tbody内のtrを狙う（より正確なパス）
    $rows = $xpath->query("//table//tbody/tr | //table/tr");
    
    if ($rows->length === 0) {
        // デバッグ用にHTMLの一部を保存（オプション）
        // file_put_contents('debug_messe.html', $html);
        throw new Exception("イベントデータが見つかりませんでした。サイトの構造が変わったか、対象月のデータがない可能性があります。");
    }
    
    foreach ($rows as $index => $row) {
        $cols = $xpath->query("td", $row);
        if ($cols->length < 3) continue;

        $date_str = trim($cols->item(0)->textContent);
        $event_name = trim($cols->item(1)->textContent);
        if (empty($event_name) || $event_name === 'イベント名') continue;

        // 会場情報の解析
        $venue_cell = $cols->item(2);
        $venue_text = "幕張メッセ";
        $venue_html = $doc->saveHTML($venue_cell);
        
        if (strpos($venue_html, 'eh1_8Icon') !== false) $venue_text .= " (1-8ホール)";
        elseif (strpos($venue_html, 'eh9_11Icon') !== false) $venue_text .= " (9-11ホール)";
        elseif (strpos($venue_html, 'conferenceIcon') !== false) $venue_text .= " (国際会議場)";
        elseif (strpos($venue_html, 'eventhallIcon') !== false) $venue_text .= " (イベントホール)";

        // 日付のパース（1/17(土) 形式または 2026.01.17(土) 形式に対応）
        $dates = [];
        $raw_dates = [];
        if (strpos($date_str, '〜') !== false) {
            $raw_dates = explode('〜', $date_str);
        } else {
            $raw_dates = [$date_str];
        }

        $parsed_start = null;
        $parsed_end = null;

        foreach ($raw_dates as $i => $rd) {
            $rd = preg_replace('/\(.*?\)/u', '', trim($rd)); // 曜日の削除
            $rd = str_replace([' ', "\n", "\r", "\t"], '', $rd);
            
            if (empty($rd)) continue;

            $d = null;
            if (strpos($rd, '.') !== false) {
                // YYYY.MM.DD形式
                $d = str_replace('.', '-', $rd);
            } elseif (strpos($rd, '/') !== false) {
                // M/D形式 -> YYYY-MM-DDに補完
                list($m, $day) = explode('/', $rd);
                $d = sprintf("%04d-%02d-%02d", $year, $m, $day);
            }

            if ($d && strtotime($d)) {
                if ($i === 0) $parsed_start = $d;
                else $parsed_end = $d;
            }
        }

        if ($parsed_start) {
            if ($parsed_end) {
                $curr = strtotime($parsed_start);
                $last = strtotime($parsed_end);
                // 最大1ヶ月分に制限（無限ループ防止）
                for($limit=0; $curr <= $last && $limit < 31; $limit++) {
                    $dates[] = date('Y-m-d', $curr);
                    $curr = strtotime('+1 day', $curr);
                }
            } else {
                $dates[] = $parsed_start;
            }
        }

        // 推奨星ランクの判定
        $star = 1;
        if (preg_match('/ライブ|コンサート|フェス|満員|幕張|ワンマン|ツアー/', $event_name)) $star = 5;
        elseif (preg_match('/大規模|展示会|見本市|コミケ|ニコニコ|オートサロン|JAPAN/', $event_name)) $star = 4;
        elseif (preg_match('/週末|イベント|即売会|体験/', $event_name)) $star = 3;
        elseif (preg_match('/学会|会議|研修|試験/', $event_name)) $star = 2;

        // DB登録
        foreach ($dates as $date) {
            $check = $pdo->prepare("SELECT id FROM events WHERE event_date = ? AND event_name = ?");
            $check->execute([$date, $event_name]);
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO events (event_date, event_name, venue, recommended_star) VALUES (?, ?, ?, ?)");
                $stmt->execute([$date, $event_name, $venue_text, $star]);
                $count++;
            }
        }
    }
    
    header("Location: calendar.php?msg=synced&count=" . $count);
    exit;

} catch (Exception $e) {
    header("Location: calendar.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
