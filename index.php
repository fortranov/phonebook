<?php
require_once 'PhoneBook.php';

$phoneBook = new PhoneBook();
$message = '';
$messageType = '';

// Обработка добавления новой записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_record') {
    $recordData = [
        $_POST['organization'] ?? '',
        $_POST['name'] ?? '',
        $_POST['position'] ?? '',
        $_POST['work_phone'] ?? '',
        $_POST['city_phone'] ?? '',
        $_POST['mobile_phone'] ?? '',
        $_POST['address'] ?? '',
        $_POST['comment'] ?? ''
    ];
    
    // Валидация данных
    $errors = $phoneBook->validateRecord($recordData);
    
    if (empty($errors)) {
        if ($phoneBook->addRecord($recordData)) {
            $message = 'Запись успешно добавлена!';
            $messageType = 'success';
        } else {
            $message = 'Ошибка при добавлении записи.';
            $messageType = 'error';
        }
    } else {
        $message = 'Ошибки валидации: ' . implode(', ', $errors);
        $messageType = 'error';
    }
}

// Обработка редактирования записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_record') {
    $recordIndex = isset($_POST['record_index']) ? (int)$_POST['record_index'] : -1;
    $recordData = [
        $_POST['organization'] ?? '',
        $_POST['name'] ?? '',
        $_POST['position'] ?? '',
        $_POST['work_phone'] ?? '',
        $_POST['city_phone'] ?? '',
        $_POST['mobile_phone'] ?? '',
        $_POST['address'] ?? '',
        $_POST['comment'] ?? ''
    ];
    
    // Валидация данных
    $errors = $phoneBook->validateRecord($recordData);
    
    if (empty($errors)) {
        if ($phoneBook->updateRecord($recordIndex, $recordData)) {
            $message = 'Запись успешно обновлена!';
            $messageType = 'success';
        } else {
            $message = 'Ошибка при обновлении записи.';
            $messageType = 'error';
        }
    } else {
        $message = 'Ошибки валидации: ' . implode(', ', $errors);
        $messageType = 'error';
    }
}

// Обработка удаления записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_record') {
    $recordIndex = isset($_POST['record_index']) ? (int)$_POST['record_index'] : -1;
    
    if ($phoneBook->deleteRecord($recordIndex)) {
        $message = 'Запись успешно удалена!';
        $messageType = 'success';
    } else {
        $message = 'Ошибка при удалении записи.';
        $messageType = 'error';
    }
}

// Обработка AJAX запросов для бесконечного скролла
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sortColumn = isset($_GET['sort']) ? (int)$_GET['sort'] : -1;
    $sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    $groupBy = isset($_GET['group']) && $_GET['group'] === '1';
    
    try {
        if ($groupBy) {
            // Для группировки возвращаем все данные сразу
            $data = $phoneBook->getData();
            if (!empty($search)) {
                $data = $phoneBook->search($search);
            }
            if ($sortColumn >= 0 && $sortColumn < count($phoneBook->getHeaders())) {
                $data = $phoneBook->sortData($sortColumn, $sortDirection);
            }
            $groupedData = $phoneBook->groupByFirstColumn($data);
            
            ob_start();
            foreach ($groupedData as $group => $rows): ?>
                <div class="group-section">
                    <h3 class="group-header">🏢 <?= htmlspecialchars($group) ?> (<?= count($rows) ?>)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach ($phoneBook->getHeaders() as $index => $header): ?>
                                    <?php if ($index > 0): ?>
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
                                        <?php if ($index > 0): ?>
                                            <td><?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?></td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach;
            $html = ob_get_clean();
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'hasMore' => false,
                'total' => count($data),
                'isGrouped' => true
            ]);
        } else {
            // Обычная пагинация
            $result = $phoneBook->getDataPaginatedWithRowspans($offset, $limit, $search, $sortColumn, $sortDirection);
            
            ob_start();
            foreach ($result['prepared_data'] as $rowIndex => $rowData): ?>
                <tr data-record-index="<?= $offset + $rowIndex ?>" class="table-row">
                    <?php foreach ($rowData['data'] as $cellIndex => $cell): ?>
                        <?php if ($cellIndex === 0): ?>
                            <?php if ($rowData['show_first_cell']): ?>
                                <td class="merged-cell" <?= $rowData['first_cell_rowspan'] > 1 ? 'rowspan="' . $rowData['first_cell_rowspan'] . '"' : '' ?>>
                                    <?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?>
                                </td>
                            <?php endif; ?>
                        <?php else: ?>
                            <td><?= !empty($search) ? $phoneBook->highlightSearch($cell, $search) : htmlspecialchars($cell ?? '') ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach;
            $html = ob_get_clean();
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'hasMore' => $result['hasMore'],
                'total' => $result['total'],
                'offset' => $result['offset'],
                'limit' => $result['limit'],
                'isGrouped' => false
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка загрузки данных: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Обработка параметров поиска и сортировки
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortColumn = isset($_GET['sort']) ? (int)$_GET['sort'] : -1;
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$groupBy = isset($_GET['group']) && $_GET['group'] === '1';

// Получаем данные для начальной загрузки
$headers = $phoneBook->getHeaders();
$initialLimit = 20; // Изначально показываем первые 20 записей

if ($groupBy) {
    // Для группировки загружаем все данные
    $data = $phoneBook->getData();
    if (!empty($search)) {
        $data = $phoneBook->search($search);
    }
    if ($sortColumn >= 0 && $sortColumn < count($headers)) {
        $data = $phoneBook->sortData($sortColumn, $sortDirection);
    }
    $groupedData = $phoneBook->groupByFirstColumn($data);
    $totalRecords = count($data);
    $hasMoreData = false;
} else {
    // Для обычного режима используем пагинацию
    $result = $phoneBook->getDataPaginatedWithRowspans(0, $initialLimit, $search, $sortColumn, $sortDirection);
    $preparedData = $result['prepared_data'];
    $totalRecords = $result['total'];
    $hasMoreData = $result['hasMore'];
    $groupedData = null;
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

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="controls">
            <form method="GET" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Поиск по всем полям (без учета регистра)..." class="search-input">
                    <button type="submit" class="btn btn-primary">🔍 Поиск</button>
                    <button type="button" class="btn btn-success" onclick="openAddModal()">➕ Добавить запись</button>
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

        <div class="table-container" id="tableContainer">
            <?php if (empty($headers)): ?>
                <div class="empty-state">
                    <h3>📋 Справочник пуст</h3>
                    <p>Загрузите CSV файл в <a href="settings.php">настройках</a></p>
                </div>
            <?php elseif ($totalRecords === 0): ?>
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
                <!-- Обычный вывод с объединением ячеек и бесконечным скроллом -->
                <table class="data-table" id="mainTable">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $index => $header): ?>
                                <th>
                                    <a href="javascript:void(0)" onclick="sortTable(<?= $index ?>)" class="sort-link">
                                        <?= htmlspecialchars($header) ?>
                                        <?php if ($sortColumn == $index): ?>
                                            <span class="sort-indicator"><?= $sortDirection == 'asc' ? '↑' : '↓' ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($preparedData as $rowIndex => $rowData): ?>
                            <tr data-record-index="<?= $rowIndex ?>" class="table-row">
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
                
                <!-- Индикатор загрузки -->
                <div id="loadingIndicator" class="loading-indicator" style="display: none;">
                    <div class="loading-spinner"></div>
                    <span>Загрузка данных...</span>
                </div>
                
                <!-- Индикатор конца данных -->
                <?php if (!$hasMoreData && $totalRecords > 0): ?>
                    <div id="endIndicator" class="end-indicator">
                        <span>📋 Все записи загружены</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalRecords > 0): ?>
            <div class="stats" id="statsContainer">
                <span id="currentCount">Показано записей: <strong><?= $groupBy ? $totalRecords : min($initialLimit, $totalRecords) ?></strong></span>
                <span id="totalCount">из <strong><?= $totalRecords ?></strong> всего</span>
                <?php if (!empty($search)): ?>
                    <span>(результаты поиска)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно для добавления записи -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Добавить новую запись</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            
            <form method="POST" class="add-form" id="addRecordForm">
                <input type="hidden" name="action" value="add_record">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="organization" class="required">Организация:</label>
                        <input type="text" id="organization" name="organization" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="name" class="required">ФИО:</label>
                        <input type="text" id="name" name="name" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Должность:</label>
                        <input type="text" id="position" name="position" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="work_phone">Служебный телефон:</label>
                        <input type="text" id="work_phone" name="work_phone" placeholder="12-34-56" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="city_phone">Городской телефон:</label>
                        <input type="text" id="city_phone" name="city_phone" placeholder="8(495)123-45-67" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_phone">Мобильный телефон:</label>
                        <input type="text" id="mobile_phone" name="mobile_phone" placeholder="8-916-123-45-67" maxlength="20">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="address">Адрес:</label>
                        <input type="text" id="address" name="address" maxlength="200">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="comment">Комментарий:</label>
                        <textarea id="comment" name="comment" rows="3" maxlength="200"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">💾 Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для редактирования записи -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Редактировать запись</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" class="edit-form" id="editRecordForm">
                <input type="hidden" name="action" value="edit_record">
                <input type="hidden" name="record_index" id="editRecordIndex">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_organization" class="required">Организация:</label>
                        <input type="text" id="edit_organization" name="organization" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_name" class="required">ФИО:</label>
                        <input type="text" id="edit_name" name="name" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_position">Должность:</label>
                        <input type="text" id="edit_position" name="position" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_work_phone">Служебный телефон:</label>
                        <input type="text" id="edit_work_phone" name="work_phone" placeholder="12-34-56" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_city_phone">Городской телефон:</label>
                        <input type="text" id="edit_city_phone" name="city_phone" placeholder="8(495)123-45-67" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_mobile_phone">Мобильный телефон:</label>
                        <input type="text" id="edit_mobile_phone" name="mobile_phone" placeholder="8-916-123-45-67" maxlength="20">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="edit_address">Адрес:</label>
                        <input type="text" id="edit_address" name="address" maxlength="200">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="edit_comment">Комментарий:</label>
                        <textarea id="edit_comment" name="comment" rows="3" maxlength="200"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div id="deleteModal" class="modal">
        <div class="modal-content delete-modal">
            <div class="modal-header delete-header">
                <h2>🗑️ Подтверждение удаления</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            
            <div class="delete-content">
                <div class="warning-icon">⚠️</div>
                <p class="delete-message">Вы действительно хотите удалить эту запись?</p>
                <div class="record-preview" id="deleteRecordPreview">
                    <!-- Здесь будет отображаться информация об удаляемой записи -->
                </div>
                <p class="delete-warning">Это действие нельзя отменить!</p>
            </div>
            
            <form method="POST" id="deleteRecordForm">
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="record_index" id="deleteRecordIndex">
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Отмена</button>
                    <button type="submit" class="btn btn-danger">🗑️ Удалить запись</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Контекстное меню -->
    <div id="contextMenu" class="context-menu">
        <div class="context-item" onclick="editRecord()">
            <span class="context-icon">✏️</span>
            <span>Редактировать</span>
        </div>
        <div class="context-item delete" onclick="deleteRecord()">
            <span class="context-icon">🗑️</span>
            <span>Удалить</span>
        </div>
    </div>

    <script>
        // Глобальные переменные для бесконечного скролла
        let currentOffset = <?= $groupBy ? 0 : $initialLimit ?>;
        let isLoading = false;
        let hasMoreData = <?= $hasMoreData ? 'true' : 'false' ?>;
        let currentTotalRecords = <?= $totalRecords ?>;
        let currentSearch = '<?= htmlspecialchars($search) ?>';
        let currentSort = <?= $sortColumn ?>;
        let currentDir = '<?= $sortDirection ?>';
        let currentGroup = <?= $groupBy ? 'true' : 'false' ?>;
        let selectedRecordIndex = -1;
        let allRecords = <?= json_encode($phoneBook->getData()) ?>;

        // Функции для управления модальными окнами
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addRecordForm').reset();
        }

        function openEditModal() {
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('editRecordForm').reset();
        }

        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Функция сортировки таблицы
        function sortTable(column) {
            const newDir = (currentSort === column && currentDir === 'asc') ? 'desc' : 'asc';
            
            // Обновляем URL
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('dir', newDir);
            
            window.location.href = url.toString();
        }

        // Функция загрузки данных через AJAX
        async function loadMoreData() {
            if (isLoading || !hasMoreData || currentGroup) {
                return;
            }

            isLoading = true;
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'flex';
            }

            try {
                const params = new URLSearchParams({
                    ajax: '1',
                    offset: currentOffset,
                    limit: 20,
                    search: currentSearch,
                    sort: currentSort,
                    dir: currentDir,
                    group: currentGroup ? '1' : '0'
                });

                const response = await fetch(`?${params}`);
                const data = await response.json();

                if (data.success) {
                    // Добавляем новые строки в таблицу
                    const tableBody = document.getElementById('tableBody');
                    if (tableBody && data.html) {
                        tableBody.insertAdjacentHTML('beforeend', data.html);
                    }

                    // Обновляем переменные состояния
                    currentOffset += data.limit;
                    hasMoreData = data.hasMore;

                    // Обновляем счетчик записей
                    updateRecordCount();

                    // Показываем индикатор конца данных
                    if (!hasMoreData) {
                        showEndIndicator();
                    }
                } else {
                    console.error('Ошибка загрузки данных:', data.error);
                }
            } catch (error) {
                console.error('Ошибка AJAX запроса:', error);
            } finally {
                isLoading = false;
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            }
        }

        // Обновление счетчика записей
        function updateRecordCount() {
            const currentCountElement = document.getElementById('currentCount');
            if (currentCountElement) {
                const tableBody = document.getElementById('tableBody');
                const currentCount = tableBody ? tableBody.children.length : currentOffset;
                currentCountElement.innerHTML = `Показано записей: <strong>${currentCount}</strong>`;
            }
        }

        // Показать индикатор конца данных
        function showEndIndicator() {
            let endIndicator = document.getElementById('endIndicator');
            if (!endIndicator) {
                endIndicator = document.createElement('div');
                endIndicator.id = 'endIndicator';
                endIndicator.className = 'end-indicator';
                endIndicator.innerHTML = '<span>📋 Все записи загружены</span>';
                
                const tableContainer = document.getElementById('tableContainer');
                if (tableContainer) {
                    tableContainer.appendChild(endIndicator);
                }
            }
            endIndicator.style.display = 'block';
        }

        // Обработчик скролла для бесконечной загрузки
        function handleScroll() {
            if (currentGroup || !hasMoreData || isLoading) {
                return;
            }

            const tableContainer = document.getElementById('tableContainer');
            if (!tableContainer) return;

            const containerRect = tableContainer.getBoundingClientRect();
            const isNearBottom = containerRect.bottom <= window.innerHeight + 200;

            if (isNearBottom) {
                loadMoreData();
            }
        }

        // Функции для контекстного меню
        function showContextMenu(event, recordIndex) {
            event.preventDefault();
            selectedRecordIndex = recordIndex;
            
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.style.display = 'block';
            contextMenu.style.left = event.pageX + 'px';
            contextMenu.style.top = event.pageY + 'px';
        }

        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
        }

        function editRecord() {
            hideContextMenu();
            
            if (selectedRecordIndex >= 0 && selectedRecordIndex < allRecords.length) {
                const record = allRecords[selectedRecordIndex];
                
                // Заполняем форму редактирования
                document.getElementById('editRecordIndex').value = selectedRecordIndex;
                document.getElementById('edit_organization').value = record[0] || '';
                document.getElementById('edit_name').value = record[1] || '';
                document.getElementById('edit_position').value = record[2] || '';
                document.getElementById('edit_work_phone').value = record[3] || '';
                document.getElementById('edit_city_phone').value = record[4] || '';
                document.getElementById('edit_mobile_phone').value = record[5] || '';
                document.getElementById('edit_address').value = record[6] || '';
                document.getElementById('edit_comment').value = record[7] || '';
                
                openEditModal();
            }
        }

        function deleteRecord() {
            hideContextMenu();
            
            if (selectedRecordIndex >= 0 && selectedRecordIndex < allRecords.length) {
                const record = allRecords[selectedRecordIndex];
                
                // Заполняем информацию об удаляемой записи
                document.getElementById('deleteRecordIndex').value = selectedRecordIndex;
                
                const preview = document.getElementById('deleteRecordPreview');
                preview.innerHTML = `
                    <div class="preview-row"><strong>Организация:</strong> ${record[0] || 'Не указано'}</div>
                    <div class="preview-row"><strong>ФИО:</strong> ${record[1] || 'Не указано'}</div>
                    <div class="preview-row"><strong>Должность:</strong> ${record[2] || 'Не указано'}</div>
                `;
                
                openDeleteModal();
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            // Добавляем обработчик скролла
            window.addEventListener('scroll', handleScroll);
            
            // Добавляем обработчики для контекстного меню
            document.addEventListener('contextmenu', function(e) {
                const row = e.target.closest('.table-row');
                if (row) {
                    const recordIndex = parseInt(row.getAttribute('data-record-index'));
                    showContextMenu(e, recordIndex);
                }
            });
            
            // Скрываем контекстное меню при клике в любом месте
            document.addEventListener('click', hideContextMenu);
            
            // Добавляем обработчик для строк таблицы (выделение)
            document.addEventListener('click', function(e) {
                const row = e.target.closest('.table-row');
                if (row) {
                    // Убираем выделение с других строк
                    document.querySelectorAll('.table-row.selected').forEach(r => r.classList.remove('selected'));
                    // Добавляем выделение к текущей строке
                    row.classList.add('selected');
                }
            });
            
            // Добавляем обработчик для поля поиска
            const searchForm = document.querySelector('.search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    // Позволяем обычную отправку формы для поиска
                    // это перезагрузит страницу с новыми параметрами
                });
            }
        });

        // Закрытие модальных окон при клике вне их
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === addModal) {
                closeAddModal();
            } else if (event.target === editModal) {
                closeEditModal();
            } else if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Закрытие модальных окон по Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeDeleteModal();
                hideContextMenu();
            }
        });

        // Автозакрытие сообщений через 5 секунд
        const messageElement = document.querySelector('.message');
        if (messageElement) {
            setTimeout(() => {
                messageElement.style.opacity = '0';
                setTimeout(() => {
                    messageElement.style.display = 'none';
                }, 300);
            }, 5000);
        }

        // Улучшенная валидация формы
        const addForm = document.getElementById('addRecordForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                const organization = document.getElementById('organization').value.trim();
                const name = document.getElementById('name').value.trim();
                
                if (!organization || !name) {
                    e.preventDefault();
                    alert('Пожалуйста, заполните обязательные поля: Организация и ФИО');
                    return;
                }
            });
        }
    </script>
</body>
</html>
