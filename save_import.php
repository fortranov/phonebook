<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Неверный запрос']);
    exit;
}

$rows = json_decode($_POST['rows'] ?? '[]', true);

if (empty($rows) || !is_array($rows)) {
    echo json_encode(['error' => 'Нет данных для импорта']);
    exit;
}

$csvFile = 'anak.csv';
$headerLine = 'организация;фио;должность;служебный;городской;мобильный;адрес;комментарий';

$needHeader = !file_exists($csvFile) || filesize($csvFile) === 0;

$handle = fopen($csvFile, 'a');
if ($handle === false) {
    echo json_encode(['error' => 'Не удалось открыть файл для записи']);
    exit;
}

if ($needHeader) {
    fwrite($handle, $headerLine . "\n");
}

$count = 0;
foreach ($rows as $row) {
    $fio       = trim($row['fio']       ?? '');
    $sluzhebny = trim($row['sluzhebny'] ?? '');
    $gorodskoy = trim($row['gorodskoy'] ?? '');

    if ($fio === '') {
        continue;
    }

    // организация;фио;должность;служебный;городской;мобильный;адрес;комментарий
    $line = implode(';', ['аппарат', $fio, '', $sluzhebny, $gorodskoy, '', '', '']);
    fwrite($handle, $line . "\n");
    $count++;
}

fclose($handle);

echo json_encode(['success' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);
