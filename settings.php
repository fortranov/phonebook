<?php
require_once 'PhoneBook.php';

$phoneBook = new PhoneBook();
$message = '';
$messageType = '';

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($phoneBook->saveFile($_FILES['csv_file'])) {
        $message = 'Файл успешно загружен!';
        $messageType = 'success';
    } else {
        $message = 'Ошибка при загрузке файла. Проверьте формат файла.';
        $messageType = 'error';
    }
}

// Обработка скачивания файла
if (isset($_GET['download'])) {
    $phoneBook->downloadFile();
}

$lastModified = $phoneBook->getLastModified();
$headers = $phoneBook->getHeaders();
$totalRecords = count($phoneBook->getData());
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Телефонный справочник</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ Настройки</h1>
            <nav>
                <a href="index.php" class="nav-link">Справочник</a>
                <a href="settings.php" class="nav-link active">Настройки</a>
            </nav>
        </header>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Информация о файле -->
            <div class="card">
                <h3>📄 Информация о файле</h3>
                <div class="file-info">
                    <?php if ($lastModified): ?>
                        <div class="info-row">
                            <span class="label">Последняя загрузка:</span>
                            <span class="value"><?= date('d.m.Y H:i:s', $lastModified) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Количество записей:</span>
                            <span class="value"><?= $totalRecords ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Размер файла:</span>
                            <span class="value"><?= file_exists('book.csv') ? round(filesize('book.csv') / 1024, 2) . ' КБ' : 'Не определен' ?></span>
                        </div>
                    <?php else: ?>
                        <p class="no-file">Файл не загружен</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Загрузка файла -->
            <div class="card">
                <h3>📤 Загрузка CSV файла</h3>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="file-input-wrapper">
                        <input type="file" name="csv_file" accept=".csv" required class="file-input" id="csv_file">
                        <label for="csv_file" class="file-label">
                            <span class="file-icon">📁</span>
                            Выберите CSV файл
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        📤 Загрузить файл
                    </button>
                </form>
                
                <div class="format-info">
                    <h4>Формат файла:</h4>
                    <p>CSV файл с разделителем ";" (точка с запятой)</p>
                    <p><strong>Структура:</strong></p>
                    <code>организация;фио;должность;служебный;городской;мобильный;адрес;комментарий</code>
                </div>
            </div>

            <!-- Скачивание файла -->
            <div class="card">
                <h3>📥 Выгрузка данных</h3>
                <?php if ($lastModified): ?>
                    <a href="?download=1" class="btn btn-secondary download-btn">
                        📥 Скачать текущий файл
                    </a>
                    <p class="download-info">
                        Скачать актуальную версию CSV файла со всеми данными
                    </p>
                <?php else: ?>
                    <p class="no-file">Нет файла для скачивания</p>
                <?php endif; ?>
            </div>

            <!-- Структура данных -->
            <?php if (!empty($headers)): ?>
            <div class="card">
                <h3>🗂️ Структура данных</h3>
                <div class="headers-list">
                    <?php foreach ($headers as $index => $header): ?>
                        <div class="header-item">
                            <span class="header-index"><?= $index + 1 ?></span>
                            <span class="header-name"><?= htmlspecialchars($header) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Пример CSV файла -->
        <div class="card example-card">
            <h3>📋 Пример CSV файла</h3>
            <div class="example-content">
                <pre><code>организация;фио;должность;служебный;городской;мобильный;адрес;комментарий
МВД России;Петров Игорь Артурович;заместитель Министра;12-34-56;8(495)619-32-45;;;
ФСБ России;Иванов Сергей Петрович;полковник;78-90-12;8(495)555-12-34;8-906-123-45-67;г. Москва;Руководитель отдела</code></pre>
            </div>
            <p class="example-note">
                <strong>Важно:</strong> Разделитель - точка с запятой (;). Пустые поля допускаются.
            </p>
        </div>

        <div class="back-link">
            <a href="index.php" class="btn btn-primary">← Вернуться к справочнику</a>
        </div>
    </div>

    <script>
        // Показываем название выбранного файла
        document.getElementById('csv_file').addEventListener('change', function(e) {
            const label = document.querySelector('.file-label');
            const fileName = e.target.files[0]?.name || 'Выберите CSV файл';
            label.innerHTML = '<span class="file-icon">📁</span>' + fileName;
        });
    </script>
</body>
</html>
