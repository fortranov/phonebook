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

    <script>
        // Функции для управления модальным окном
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Блокируем прокрутку фона
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Восстанавливаем прокрутку
            document.getElementById('addRecordForm').reset(); // Очищаем форму
        }

        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeAddModal();
            }
        }

        // Закрытие модального окна по Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
            }
        });

        // Показываем название выбранного файла (из settings.php)
        const fileInput = document.getElementById('csv_file');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const label = document.querySelector('.file-label');
                const fileName = e.target.files[0]?.name || 'Выберите CSV файл';
                label.innerHTML = '<span class="file-icon">📁</span>' + fileName;
            });
        }

        // Автозакрытие сообщений через 5 секунд
        const messageElement = document.querySelector('.message');
        if (messageElement) {
            // Если это сообщение об успехе после добавления записи, закрываем модальное окно
            if (messageElement.classList.contains('success') && window.location.search.includes('POST')) {
                closeAddModal();
            }
            
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
