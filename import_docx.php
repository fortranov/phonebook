<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['docx_file'])) {
    echo json_encode(['error' => 'Неверный запрос']);
    exit;
}

$fioCol   = max(0, (int)($_POST['fio_col']   ?? 1) - 1);
$phoneCol = max(0, (int)($_POST['phone_col'] ?? 2) - 1);

$file = $_FILES['docx_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Ошибка загрузки файла (код ' . $file['error'] . ')']);
    exit;
}

if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx') {
    echo json_encode(['error' => 'Поддерживаются только файлы формата .docx']);
    exit;
}

if (!class_exists('ZipArchive')) {
    echo json_encode(['error' => 'Расширение ZipArchive не установлено на сервере']);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($file['tmp_name']) !== true) {
    echo json_encode(['error' => 'Не удалось открыть файл DOCX (возможно, файл повреждён)']);
    exit;
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml === false) {
    echo json_encode(['error' => 'Не удалось прочитать содержимое файла']);
    exit;
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($xml);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$tables = $xpath->query('//w:tbl');
if ($tables->length === 0) {
    echo json_encode(['error' => 'В документе не найдено таблиц']);
    exit;
}

// Берём первую таблицу в документе
$table = $tables->item(0);
$rows  = $xpath->query('.//w:tr', $table);

// Получаем текст ячейки, объединяя параграфы через \n
function getCellText(DOMXPath $xpath, DOMNode $cell): string {
    $paragraphs = $xpath->query('.//w:p', $cell);
    $lines = [];
    foreach ($paragraphs as $p) {
        $texts = $xpath->query('.//w:t', $p);
        $line  = '';
        foreach ($texts as $t) {
            $line .= $t->textContent;
        }
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return implode("\n", $lines);
}

// Разбираем строку с телефонами: возвращает ['sluzhebny' => ..., 'gorodskoy' => ...]
function parsePhones(string $raw): array {
    // Разбиваем по переносам строк, запятым, точкам с запятой
    $parts = preg_split('/[\n\r,;]+/', $raw);
    $sluzhebny = '';
    $gorodskoy = '';

    foreach ($parts as $part) {
        $phone = trim($part);
        if ($phone === '') {
            continue;
        }
        // Городской: начинается со скобки, например (495)123-34-45
        if ($gorodskoy === '' && preg_match('/^\(/', $phone)) {
            $gorodskoy = $phone;
        }
        // Служебный: 6 цифр с двумя дефисами (NN-NN-NN), возможно после произвольного текста
        // Например: "34-56-78", "пульт ИЗ: 45-23-78", "ТО: 87-32-45"
        elseif ($sluzhebny === '' && preg_match('/\d{2}-\d{2}-\d{2}/', $phone)) {
            $sluzhebny = $phone;
        }
    }

    return ['sluzhebny' => $sluzhebny, 'gorodskoy' => $gorodskoy];
}

$result   = [];
$isHeader = true;

foreach ($rows as $row) {
    if ($isHeader) {
        $isHeader = false;
        continue; // пропускаем заголовочную строку таблицы
    }

    $cells     = $xpath->query('.//w:tc', $row);
    $cellTexts = [];
    foreach ($cells as $cell) {
        $cellTexts[] = getCellText($xpath, $cell);
    }

    $fio      = trim($cellTexts[$fioCol]   ?? '');
    $phoneRaw = trim($cellTexts[$phoneCol] ?? '');

    if ($fio === '') {
        continue;
    }

    $phones = parsePhones($phoneRaw);

    $result[] = [
        'fio'       => $fio,
        'sluzhebny' => $phones['sluzhebny'],
        'gorodskoy' => $phones['gorodskoy'],
    ];
}

if (empty($result)) {
    echo json_encode(['error' => 'Не найдено данных в таблице (проверьте номера колонок)']);
    exit;
}

echo json_encode(['data' => $result], JSON_UNESCAPED_UNICODE);
