<?php
require_once 'PhoneBook.php';

$phoneBook = new PhoneBook();

// Обработка параметров поиска и сортировки
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortColumn = isset($_GET['sort']) ? (int)$_GET['sort'] : -1;
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$groupBy = isset($_GET['group']) && $_GET['group'] === '1';

// Получаем данные
$headers = $phoneBook->getHeaders();
$data = $phoneBook->getData();

// Применяем поиск
if (!empty($search)) {
    $data = $phoneBook->search($search);
}

// Применяем сортировку
if ($sortColumn >= 0 && $sortColumn < count($headers)) {
    $data = $phoneBook->sortData($sortColumn, $sortDirection);
}

// Применяем группировку
$groupedData = null;
if ($groupBy) {
    $groupedData = $phoneBook->groupByFirstColumn($data);
}

$lastModified = $phoneBook->getLastModified();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Телефонный справочник</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📞 Телефонный справочник</h1>
            <nav>
                <a href="index.php" class="nav-link active">Справочник</a>
                <a href="settings.php" class="nav-link">Настройки</a>
            </nav>
        </header>

        <div class="controls">
            <form method="GET" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Поиск по всем полям (без учета регистра)..." class="search-input">
                    <button type="submit" class="btn btn-primary">🔍 Поиск</button>
                </div>
                
                <div class="filter-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="group" value="1" <?= $groupBy ? 'checked' : '' ?>>
                        Группировать по организации
                    </label>
                </div>
                
                <?php if (!empty($search) || $groupBy): ?>
                    <a href="index.php" class="btn btn-secondary">Сбросить фильтры</a>
                <?php endif; ?>
                
                <!-- Скрытые поля для сохранения сортировки -->
                <?php if ($sortColumn >= 0): ?>
                    <input type="hidden" name="sort" value="<?= $sortColumn ?>">
                    <input type="hidden" name="dir" value="<?= $sortDirection ?>">
                <?php endif; ?>
            </form>
        </div>

        <?php if ($lastModified): ?>
            <div class="info-bar">
                Последнее обновление данных: <?= date('d.m.Y H:i:s', $lastModified) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($headers)): ?>
                <div class="empty-state">
                    <h3>📋 Справочник пуст</h3>
                    <p>Загрузите CSV файл в <a href="settings.php">настройках</a></p>
                </div>
            <?php elseif (empty($data)): ?>
                <div class="empty-state">
                    <h3>🔍 Ничего не найдено</h3>
                    <p>По вашему запросу "<?= htmlspecialchars($search) ?>" ничего не найдено</p>
                </div>
            <?php elseif ($groupBy && $groupedData): ?>
                <!-- Группированный вывод -->
                <?php foreach ($groupedData as $group => $rows): ?>
                    <div class="group-section">
                        <h3 class="group-header">🏢 <?= htmlspecialchars($group) ?> (<?= count($rows) ?>)</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <?php foreach ($headers as $index => $header): ?>
                                        <?php if ($index > 0): // Пропускаем первый столбец (организация) ?>
                                            <th>
                                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $index, 'dir' => ($sortColumn == $index && $sortDirection == 'asc') ? 'desc' : 'asc'])) ?>" 
                                                   class="sort-link">
                                                    <?= htmlspecialchars($header) ?>
                                                    <?php if ($sortColumn == $index): ?>
                                                        <span class="sort-indicator"><?= $sortDirection == 'asc' ? '↑' : '↓' ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $index => $cell): ?>
                                            <?php if ($index > 0): // Пропускаем первый столбец ?>
                                                <td><?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Обычный вывод с объединением ячеек -->
                <?php
                // Подготавливаем данные с информацией о rowspan
                $preparedData = $phoneBook->prepareDataWithRowspans($data);
                ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $index => $header): ?>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $index, 'dir' => ($sortColumn == $index && $sortDirection == 'asc') ? 'desc' : 'asc'])) ?>" 
                                       class="sort-link">
                                        <?= htmlspecialchars($header) ?>
                                        <?php if ($sortColumn == $index): ?>
                                            <span class="sort-indicator"><?= $sortDirection == 'asc' ? '↑' : '↓' ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preparedData as $rowData): ?>
                            <tr>
                                <?php foreach ($rowData['data'] as $cellIndex => $cell): ?>
                                    <?php if ($cellIndex === 0): ?>
                                        <!-- Первый столбец с возможным объединением -->
                                        <?php if ($rowData['show_first_cell']): ?>
                                            <td class="merged-cell" <?= $rowData['first_cell_rowspan'] > 1 ? 'rowspan="' . $rowData['first_cell_rowspan'] . '"' : '' ?>>
                                                <?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?>
                                            </td>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Остальные столбцы -->
                                        <td><?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($data)): ?>
            <div class="stats">
                Показано записей: <strong><?= count($data) ?></strong>
                <?php if (!empty($search)): ?>
                    из <?= count($phoneBook->getData()) ?> всего
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
