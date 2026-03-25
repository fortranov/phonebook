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

            <!-- Импорт из Word -->
            <div class="card">
                <h3>📝 Импорт из Word (.docx)</h3>
                <form id="docxImportForm" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <input type="file" name="docx_file" accept=".docx" required class="file-input" id="docx_file">
                        <label for="docx_file" class="file-label">
                            <span class="file-icon">📁</span>
                            Выберите DOCX файл
                        </label>
                    </div>
                    <div class="docx-cols">
                        <div class="col-input-group">
                            <label for="fio_col">Колонка ФИО (№):</label>
                            <input type="number" id="fio_col" name="fio_col" value="1" min="1" class="col-input">
                        </div>
                        <div class="col-input-group">
                            <label for="phone_col">Колонка телефонов (№):</label>
                            <input type="number" id="phone_col" name="phone_col" value="2" min="1" class="col-input">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="docxSubmitBtn">
                        📥 Загрузить и разобрать
                    </button>
                    <div id="docxError" class="message error" style="display:none;margin-top:10px;"></div>
                </form>
                <div class="format-info" style="margin-top:14px;">
                    <p>Первая строка таблицы в документе считается заголовком и пропускается.</p>
                    <p>Телефоны в ячейке разделяйте запятой или переносом строки.</p>
                    <p>Данные будут записаны в файл <strong>anak.csv</strong>.</p>
                </div>
            </div>
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

    <!-- Модальное окно предпросмотра импорта -->
    <div id="importModal" class="import-modal-overlay" style="display:none;">
        <div class="import-modal">
            <div class="import-modal-header">
                <h3>📋 Предпросмотр импорта</h3>
                <p class="import-modal-subtitle">Выберите записи для импорта в <strong>anak.csv</strong></p>
            </div>
            <div class="import-modal-actions">
                <button id="modalImportBtn" class="btn btn-primary">✅ Импортировать</button>
                <button id="modalCancelBtn" class="btn btn-secondary">✖ Отмена</button>
                <label class="select-all-label">
                    <input type="checkbox" id="selectAllChk" checked> Выбрать все
                </label>
            </div>
            <div class="import-modal-body">
                <table class="import-preview-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>ФИО</th>
                            <th>Служебный</th>
                            <th>Городской</th>
                        </tr>
                    </thead>
                    <tbody id="importPreviewBody"></tbody>
                </table>
            </div>
            <div id="importModalMsg" style="display:none;margin:10px 16px 0;"></div>
        </div>
    </div>

    <style>
        /* Стили для блока импорта из Word */
        .docx-cols {
            display: flex;
            gap: 20px;
            margin: 14px 0;
            flex-wrap: wrap;
        }
        .col-input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .col-input-group label {
            font-size: 0.9em;
            color: #555;
        }
        .col-input {
            width: 90px;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }

        /* Модальное окно */
        .import-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .import-modal {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 48px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 780px;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .import-modal-header {
            padding: 18px 20px 10px;
            border-bottom: 1px solid #eee;
        }
        .import-modal-header h3 {
            font-size: 1.3em;
            margin-bottom: 4px;
        }
        .import-modal-subtitle {
            font-size: 0.88em;
            color: #666;
        }
        .import-modal-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            border-bottom: 1px solid #eee;
            background: #fafafa;
            flex-wrap: wrap;
        }
        .select-all-label {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9em;
            cursor: pointer;
            color: #444;
        }
        .select-all-label input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .import-modal-body {
            overflow-y: auto;
            flex: 1;
            padding: 0 20px 16px;
        }
        .import-preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            margin-top: 12px;
        }
        .import-preview-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f0f0f0;
            border-bottom: 2px solid #ddd;
            white-space: nowrap;
        }
        .import-preview-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .import-preview-table tr:hover td {
            background: #f7f7ff;
        }
        .import-preview-table input[type=checkbox] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .phone-badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 0.85em;
        }
        .phone-badge.office  { background: #e8f4e8; color: #2d6a2d; }
        .phone-badge.city    { background: #e8eef8; color: #1a3a6a; }
        .phone-badge.empty   { color: #aaa; font-style: italic; }
    </style>

    <script>
        // Показываем название выбранного файла (CSV)
        document.getElementById('csv_file').addEventListener('change', function(e) {
            const label = document.querySelector('.file-label');
            const fileName = e.target.files[0]?.name || 'Выберите CSV файл';
            label.innerHTML = '<span class="file-icon">📁</span>' + fileName;
        });

        // Показываем название выбранного DOCX файла
        document.getElementById('docx_file').addEventListener('change', function(e) {
            const label = document.querySelector('label[for="docx_file"]');
            const fileName = e.target.files[0]?.name || 'Выберите DOCX файл';
            label.innerHTML = '<span class="file-icon">📄</span>' + fileName;
        });

        // ---- Импорт из DOCX ----
        let parsedRows = []; // хранит все распарсенные строки

        document.getElementById('docxImportForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const errorDiv = document.getElementById('docxError');
            const submitBtn = document.getElementById('docxSubmitBtn');
            errorDiv.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Обработка...';

            const formData = new FormData(this);

            fetch('import_docx.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                } else {
                    parsedRows = data.data;
                    showImportModal(parsedRows);
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Ошибка соединения с сервером';
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '📥 Загрузить и разобрать';
            });
        });

        function showImportModal(rows) {
            const tbody = document.getElementById('importPreviewBody');
            tbody.innerHTML = '';

            rows.forEach(function(row, idx) {
                const tr = document.createElement('tr');

                const slBadge = row.sluzhebny
                    ? '<span class="phone-badge office">' + escHtml(row.sluzhebny) + '</span>'
                    : '<span class="phone-badge empty">—</span>';

                const gtBadge = row.gorodskoy
                    ? '<span class="phone-badge city">' + escHtml(row.gorodskoy) + '</span>'
                    : '<span class="phone-badge empty">—</span>';

                tr.innerHTML =
                    '<td><input type="checkbox" class="row-chk" data-idx="' + idx + '" checked></td>' +
                    '<td>' + escHtml(row.fio) + '</td>' +
                    '<td>' + slBadge + '</td>' +
                    '<td>' + gtBadge + '</td>';

                tbody.appendChild(tr);
            });

            // Сброс "выбрать все"
            document.getElementById('selectAllChk').checked = true;
            document.getElementById('importModalMsg').style.display = 'none';

            document.getElementById('importModal').style.display = 'flex';
        }

        // "Выбрать все" чекбокс
        document.getElementById('selectAllChk').addEventListener('change', function() {
            document.querySelectorAll('.row-chk').forEach(chk => chk.checked = this.checked);
        });

        // Отмена
        document.getElementById('modalCancelBtn').addEventListener('click', function() {
            document.getElementById('importModal').style.display = 'none';
        });

        // Клик вне модального окна — закрываем
        document.getElementById('importModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        // Импортировать
        document.getElementById('modalImportBtn').addEventListener('click', function() {
            const selected = [];
            document.querySelectorAll('.row-chk:checked').forEach(function(chk) {
                selected.push(parsedRows[parseInt(chk.dataset.idx)]);
            });

            if (selected.length === 0) {
                showModalMsg('Не выбрано ни одной записи', 'error');
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ Сохранение...';

            const fd = new FormData();
            fd.append('rows', JSON.stringify(selected));

            fetch('save_import.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    showModalMsg(data.error, 'error');
                } else {
                    showModalMsg('Импортировано записей: ' + data.count + ' → anak.csv', 'success');
                    setTimeout(function() {
                        document.getElementById('importModal').style.display = 'none';
                        location.reload();
                    }, 1800);
                }
            })
            .catch(() => showModalMsg('Ошибка соединения с сервером', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = '✅ Импортировать';
            });
        });

        function showModalMsg(text, type) {
            const el = document.getElementById('importModalMsg');
            el.textContent = text;
            el.className = 'message ' + type;
            el.style.display = 'block';
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>
