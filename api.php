<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'PhoneBook.php';

// Поддерживаемые действия
$action = $_GET['action'] ?? 'search';

if ($action === 'search') {
    $q      = trim($_GET['q'] ?? '');
    $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    // Собираем данные из всех CSV-файлов в директории проекта
    $allData = [];
    foreach (glob(__DIR__ . '/*.csv') as $csvFile) {
        $pb   = new PhoneBook(basename($csvFile));
        $rows = empty($q) ? $pb->getData() : $pb->search($q);
        $allData = array_merge($allData, $rows);
    }

    $total   = count($allData);
    $slice   = array_slice($allData, $offset, $limit);

    $records = [];
    foreach ($slice as $row) {
        $records[] = [
            'организация' => $row[0] ?? '',
            'фио'         => $row[1] ?? '',
            'должность'   => $row[2] ?? '',
            'служебный'   => $row[3] ?? '',
            'городской'   => $row[4] ?? '',
            'мобильный'   => $row[5] ?? '',
            'адрес'       => $row[6] ?? '',
            'комментарий' => $row[7] ?? '',
        ];
    }

    echo json_encode([
        'success' => true,
        'total'   => $total,
        'offset'  => $offset,
        'limit'   => $limit,
        'count'   => count($records),
        'records' => $records,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Неизвестное действие: ' . htmlspecialchars($action),
    ], JSON_UNESCAPED_UNICODE);
}
