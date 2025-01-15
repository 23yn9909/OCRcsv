<?php
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once 'config.php';

define('CSV_FILE_PATH', __DIR__ . '/ocr_search_results.csv');
define('OCR_LOG_FILE', __DIR__ . '/ocr_search.log');

function performOcr($imagePath) {
    $headers = [
        "Ocp-Apim-Subscription-Key: " . AZURE_VISION_API_KEY,
        "Content-Type: application/octet-stream"
    ];

    $url = AZURE_VISION_API_ENDPOINT . "vision/v3.2/read/analyze";
    $imageData = file_get_contents($imagePath);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersResponse = substr($response, 0, $headerSize);

    curl_close($ch);

    preg_match('/Operation-Location: (.+)/i', $headersResponse, $matches);
    $operationLocation = isset($matches[1]) ? trim($matches[1]) : null;

    if (empty($operationLocation)) {
        return null;
    }

    return fetchOcrResult($operationLocation, $headers);
}

function fetchOcrResult($operationLocation, $headers) {
    $retryCount = 10;
    $waitTime = 2;

    for ($i = 0; $i < $retryCount; $i++) {
        sleep($waitTime);
        $ch = curl_init($operationLocation);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (!empty($result['analyzeResult']['readResults'])) {
                return $result;
            }
        }
    }

    return null;
}

function extractValidEntries($ocrResult) {
    $lines = [];
    foreach ($ocrResult['analyzeResult']['readResults'] as $page) {
        foreach ($page['lines'] as $line) {
            $lines[] = $line['text'];
        }
    }

    // OCR結果をログファイルに出力
    file_put_contents(OCR_LOG_FILE, implode("\n", $lines) . "\n", FILE_APPEND);

    $processedData = [];
    $lastName = null;

    foreach ($lines as $line) {
        $line = trim($line); // 空白削除

        // 数値と数値のパターン (例: "46128 118") は無視する
        if (preg_match('/^\d+\s+\d+$/', $line)) {
            continue;
        }

        // 無視する条件: 時間や特殊なパターン
        if (preg_match('/\d+時間|\d+分|\./u', $line)) {
            continue; // "2時間" や "3分"、".（小数点）" を含む行はスキップ
        }

        // 単独の1文字は無視する (例: "る")
        if (mb_strlen($line) === 1) {
            continue;
        }

        // ゴミテキストのパターン (特定キーワードを含む場合)
        if (preg_match('/エンブレム|修正|前回の戦績/', $line)) {
            continue;
        }

        // 得点の条件: 100の倍数かつ0以上3000以下、かつ0ではない
        if (is_numeric($line) && $line > 0 && $line <= 3000 && $line % 100 === 0) {
            if ($lastName) {
                // ユーザー名とスコアを保存
                $processedData[] = ['name' => $lastName, 'score' => (int)$line];
                $lastName = null; // 次のユーザーの処理に備える
            }
        } 
        // ユーザー名のパターン: 数値以外の文字列 (記号や空白も許容)
        elseif (preg_match('/^[\p{L}a-zA-Z0-9\s「」]+$/u', $line)) {
            $lastName = $line; // ユーザー名候補として保存
        }
    }

    return $processedData;
}

// CSVファイルにエントリを保存する関数
function saveToCsv($entries, $date) {
    $file = fopen(CSV_FILE_PATH, 'a');

    if ($file === false) {
        return;
    }

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $score = $entry['score'];
        fputcsv($file, [$date, $name, $score]);
    }

    fclose($file);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $date = $_POST['date'];
    $tmpName = $_FILES['image']['tmp_name'];

    $ocrResult = performOcr($tmpName);
    if ($ocrResult) {
        $processedEntries = extractValidEntries($ocrResult);
        saveToCsv($processedEntries, $date);

        echo json_encode([
            "status" => "completed",
            "processed_entries" => $processedEntries,
            "failed_files" => [],
            "data" => $processedEntries
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "OCR処理に失敗しました"
        ]);
    }
}