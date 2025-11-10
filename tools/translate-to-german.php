<?php
// tools/translate-to-german.php
// Bulk add German ('de') translations into data/data.json
// Uses Google Translate public endpoint. Make a backup before running.

const DATA_FILE = __DIR__ . '/../data/data.json';
const BACKUP_FILE = __DIR__ . '/../data/data.json.bak';
const TARGET_LANG = 'de';

if (!file_exists(DATA_FILE)) {
    fwrite(STDERR, "Data file not found: " . DATA_FILE . PHP_EOL);
    exit(1);
}

// ensure backup exists
if (!file_exists(BACKUP_FILE)) {
    if (!copy(DATA_FILE, BACKUP_FILE)) {
        fwrite(STDERR, "Backup failed." . PHP_EOL);
        exit(1);
    }
    echo "Backup created: " . BACKUP_FILE . PHP_EOL;
}

$dataJson = file_get_contents(DATA_FILE);
$countries = json_decode($dataJson, true);
if (!is_array($countries)) {
    fwrite(STDERR, "Failed to parse JSON." . PHP_EOL);
    exit(1);
}

function translateText(string $text, string $sourceLang = 'en', string $targetLang = TARGET_LANG): string
{
    $text = trim($text);
    if ($text === '') return '';

    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . urlencode($sourceLang)
         . '&tl=' . urlencode($targetLang) . '&dt=t&q=' . urlencode($text);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'php-translate-script/1.0'
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $resp === '' || $err) {
        return $text; // fallback: original
    }

    $arr = json_decode($resp, true);
    if (!is_array($arr) || !isset($arr[0][0][0])) {
        return $text;
    }

    // Join all translated chunks
    $out = '';
    foreach ($arr[0] as $chunk) {
        $out .= $chunk[0] ?? '';
    }

    return $out ?: $text;
}

$updatedCount = 0;
$entriesChecked = 0;

foreach ($countries as $i => $c) {
    $entriesChecked++;
    // Ensure keys
    if (!isset($c['descriptions'])) $c['descriptions'] = [];
    if (!isset($c['durations'])) $c['durations'] = [];

    // Decide source language preference: en -> tr
    // For descriptions
    if (empty($c['descriptions'][TARGET_LANG] ?? '')) {
        $source = '';
        $text = '';
        if (!empty($c['descriptions']['en'] ?? '')) {
            $source = 'en';
            $text = $c['descriptions']['en'];
        } elseif (!empty($c['descriptions']['tr'] ?? '')) {
            $source = 'tr';
            $text = $c['descriptions']['tr'];
        }

        if ($text !== '') {
            $trans = translateText($text, $source, TARGET_LANG);
            $countries[$i]['descriptions'][TARGET_LANG] = $trans;
            $updatedCount++;
            echo "[$i] Translated description for {$c['country']}\n";
            // short delay
            usleep(250000);
        }
    }

    // durations
    if (empty($c['durations'][TARGET_LANG] ?? '')) {
        $source = '';
        $text = '';
        if (!empty($c['durations']['en'] ?? '')) {
            $source = 'en';
            $text = $c['durations']['en'];
        } elseif (!empty($c['durations']['tr'] ?? '')) {
            $source = 'tr';
            $text = $c['durations']['tr'];
        }

        if ($text !== '') {
            $trans = translateText($text, $source, TARGET_LANG);
            $countries[$i]['durations'][TARGET_LANG] = $trans;
            $updatedCount++;
            echo "[$i] Translated duration for {$c['country']}\n";
            usleep(250000);
        }
    }

    // Update last_update
    $countries[$i]['last_update'] = date('Y-m-d');
}

// Write back
$ok = file_put_contents(DATA_FILE, json_encode(array_values($countries), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($ok === false) {
    fwrite(STDERR, "Failed to write updated data file." . PHP_EOL);
    exit(1);
}

echo "\nDone. Checked: {$entriesChecked}, translated fields added: {$updatedCount}\n";
echo "Backup is at: " . BACKUP_FILE . PHP_EOL;

return 0;
