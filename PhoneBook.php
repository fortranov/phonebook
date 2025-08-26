<?php

class PhoneBook {
    private $csvFile;
    private $headers;
    private $data;
    
    public function __construct($csvFile = 'book.csv') {
        $this->csvFile = $csvFile;
        $this->loadData();
    }
    
    private function loadData() {
        $this->data = [];
        $this->headers = [];
        
        if (!file_exists($this->csvFile)) {
            return;
        }
        
        $handle = fopen($this->csvFile, 'r');
        if ($handle !== FALSE) {
            // Читаем заголовки
            if (($line = fgets($handle)) !== false) {
                $this->headers = str_getcsv(trim($line), ';');
            }
            
            // Читаем данные
            while (($line = fgets($handle)) !== false) {
                $row = str_getcsv(trim($line), ';');
                if (!empty(array_filter($row))) { // Пропускаем пустые строки
                    $this->data[] = $row;
                }
            }
            fclose($handle);
        }
    }
    
    public function getHeaders() {
        return $this->headers;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function search($query) {
        if (empty($query)) {
            return $this->data;
        }
        
        // Приводим поисковый запрос к нижнему регистру и разбиваем на слова
        $query = mb_strtolower(trim($query), 'UTF-8');
        $queryWords = array_filter(explode(' ', $query)); // Убираем пустые элементы
        
        $results = [];
        foreach ($this->data as $row) {
            $rowText = mb_strtolower(implode(' ', $row), 'UTF-8');
            
            // Проверяем, содержит ли строка все слова из поискового запроса
            $allWordsFound = true;
            foreach ($queryWords as $word) {
                if (mb_strpos($rowText, $word, 0, 'UTF-8') === false) {
                    $allWordsFound = false;
                    break;
                }
            }
            
            if ($allWordsFound) {
                $results[] = $row;
            }
        }
        return $results;
    }
    
    public function highlightSearch($text, $query) {
        if (empty($query) || empty($text)) {
            return htmlspecialchars($text ?? '');
        }
        
        $result = htmlspecialchars($text);
        $queryWords = array_filter(explode(' ', mb_strtolower(trim($query), 'UTF-8')));
        
        // Подсвечиваем каждое слово из запроса
        foreach ($queryWords as $word) {
            if (mb_strlen($word, 'UTF-8') > 0) {
                // Используем регулярное выражение для поиска слов без учета регистра
                $pattern = '/(' . preg_quote($word, '/') . ')/iu';
                $result = preg_replace(
                    $pattern, 
                    '<mark class="search-highlight">$1</mark>', 
                    $result
                );
            }
        }
        
        return $result;
    }
    
    public function sortData($column, $direction = 'asc') {
        $data = $this->data;
        
        usort($data, function($a, $b) use ($column, $direction) {
            $valueA = isset($a[$column]) ? $a[$column] : '';
            $valueB = isset($b[$column]) ? $b[$column] : '';
            
            // Используем mb_strtolower для корректной работы с UTF-8 и регистронезависимого сравнения
            $result = strcmp(
                mb_strtolower($valueA, 'UTF-8'), 
                mb_strtolower($valueB, 'UTF-8')
            );
            return $direction === 'desc' ? -$result : $result;
        });
        
        return $data;
    }
    
    public function groupByFirstColumn($data = null) {
        $dataToGroup = $data ?? $this->data;
        $grouped = [];
        
        foreach ($dataToGroup as $row) {
            $groupKey = isset($row[0]) ? $row[0] : 'Без группы';
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }
            $grouped[$groupKey][] = $row;
        }
        
        return $grouped;
    }
    
    public function prepareDataWithRowspans($data = null) {
        $dataToProcess = $data ?? $this->data;
        $result = [];
        $prevFirstColumn = null;
        $currentGroupStartIndex = 0;
        
        // Сначала сортируем данные по первому столбцу для группировки
        $sortedData = $dataToProcess;
        usort($sortedData, function($a, $b) {
            $valueA = isset($a[0]) ? mb_strtolower($a[0], 'UTF-8') : '';
            $valueB = isset($b[0]) ? mb_strtolower($b[0], 'UTF-8') : '';
            return strcmp($valueA, $valueB);
        });
        
        // Проходим по отсортированным данным и определяем rowspan для каждой строки
        for ($i = 0; $i < count($sortedData); $i++) {
            $currentFirstColumn = isset($sortedData[$i][0]) ? $sortedData[$i][0] : '';
            $currentFirstColumnLower = mb_strtolower($currentFirstColumn, 'UTF-8');
            
            // Если это новая группа или последняя строка (сравниваем без учета регистра)
            if ($prevFirstColumn !== null && $currentFirstColumnLower !== mb_strtolower($prevFirstColumn, 'UTF-8')) {
                // Устанавливаем rowspan для всех строк предыдущей группы
                $groupSize = $i - $currentGroupStartIndex;
                for ($j = $currentGroupStartIndex; $j < $i; $j++) {
                    $result[$j] = [
                        'data' => $sortedData[$j],
                        'first_cell_rowspan' => ($j === $currentGroupStartIndex) ? $groupSize : 0,
                        'show_first_cell' => ($j === $currentGroupStartIndex)
                    ];
                }
                $currentGroupStartIndex = $i;
            }
            
            $prevFirstColumn = $currentFirstColumn;
        }
        
        // Обрабатываем последнюю группу
        if (!empty($sortedData)) {
            $groupSize = count($sortedData) - $currentGroupStartIndex;
            for ($j = $currentGroupStartIndex; $j < count($sortedData); $j++) {
                $result[$j] = [
                    'data' => $sortedData[$j],
                    'first_cell_rowspan' => ($j === $currentGroupStartIndex) ? $groupSize : 0,
                    'show_first_cell' => ($j === $currentGroupStartIndex)
                ];
            }
        }
        
        return $result;
    }
    
    public function addRecord($recordData) {
        if (empty($recordData) || !is_array($recordData)) {
            return false;
        }
        
        // Проверяем, что у нас есть все необходимые поля
        $headers = $this->getHeaders();
        if (empty($headers)) {
            return false;
        }
        
        // Дополняем запись до нужного количества полей
        $record = array_pad($recordData, count($headers), '');
        
        // Открываем файл для добавления
        $handle = fopen($this->csvFile, 'a');
        if ($handle === FALSE) {
            return false;
        }
        
        // Записываем новую строку
        $csvLine = implode(';', array_map(function($field) {
            // Экранируем специальные символы
            return str_replace([';', "\n", "\r"], ['\\;', '\\n', '\\r'], $field);
        }, $record));
        
        if (fwrite($handle, "\n" . $csvLine) === FALSE) {
            fclose($handle);
            return false;
        }
        
        fclose($handle);
        
        // Перезагружаем данные
        $this->loadData();
        
        return true;
    }
    
    public function validateRecord($recordData) {
        $errors = [];
        
        // Проверяем обязательные поля (организация и ФИО)
        if (empty(trim($recordData[0] ?? ''))) {
            $errors[] = 'Организация обязательна для заполнения';
        }
        
        if (empty(trim($recordData[1] ?? ''))) {
            $errors[] = 'ФИО обязательно для заполнения';
        }
        
        return $errors;
    }
    
    public function getDataPaginated($offset = 0, $limit = 20, $searchQuery = '', $sortColumn = -1, $sortDirection = 'asc') {
        // Получаем данные с учетом поиска
        $data = $this->data;
        if (!empty($searchQuery)) {
            $data = $this->search($searchQuery);
        }
        
        // Применяем сортировку
        if ($sortColumn >= 0 && $sortColumn < count($this->headers)) {
            $data = $this->sortDataArray($data, $sortColumn, $sortDirection);
        }
        
        // Получаем срез данных для пагинации
        $totalRecords = count($data);
        $paginatedData = array_slice($data, $offset, $limit);
        
        return [
            'data' => $paginatedData,
            'total' => $totalRecords,
            'hasMore' => ($offset + $limit) < $totalRecords,
            'offset' => $offset,
            'limit' => $limit
        ];
    }
    
    public function getDataPaginatedWithRowspans($offset = 0, $limit = 20, $searchQuery = '', $sortColumn = -1, $sortDirection = 'asc') {
        // Получаем данные с пагинацией
        $result = $this->getDataPaginated($offset, $limit, $searchQuery, $sortColumn, $sortDirection);
        
        // Подготавливаем данные с rowspan для всех данных (не только для текущей порции)
        // Это нужно для корректного объединения ячеек
        $allData = $this->data;
        if (!empty($searchQuery)) {
            $allData = $this->search($searchQuery);
        }
        
        if ($sortColumn >= 0 && $sortColumn < count($this->headers)) {
            $allData = $this->sortDataArray($allData, $sortColumn, $sortDirection);
        }
        
        $preparedData = $this->prepareDataWithRowspans($allData);
        
        // Получаем только нужную порцию подготовленных данных
        $paginatedPreparedData = array_slice($preparedData, $offset, $limit);
        
        $result['prepared_data'] = $paginatedPreparedData;
        return $result;
    }
    
    private function sortDataArray($data, $column, $direction = 'asc') {
        usort($data, function($a, $b) use ($column, $direction) {
            $valueA = isset($a[$column]) ? $a[$column] : '';
            $valueB = isset($b[$column]) ? $b[$column] : '';
            
            // Используем mb_strtolower для корректной работы с UTF-8 и регистронезависимого сравнения
            $result = strcmp(
                mb_strtolower($valueA, 'UTF-8'), 
                mb_strtolower($valueB, 'UTF-8')
            );
            return $direction === 'desc' ? -$result : $result;
        });
        
        return $data;
    }
    
    public function saveFile($uploadedFile) {
        if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
            $tmpName = $uploadedFile['tmp_name'];
            $backupName = $this->csvFile . '.backup.' . date('Y-m-d_H-i-s');
            
            // Создаем резервную копию
            if (file_exists($this->csvFile)) {
                copy($this->csvFile, $backupName);
            }
            
            // Сохраняем новый файл
            if (move_uploaded_file($tmpName, $this->csvFile)) {
                $this->loadData(); // Перезагружаем данные
                return true;
            }
        }
        return false;
    }
    
    public function downloadFile() {
        if (file_exists($this->csvFile)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="phonebook_' . date('Y-m-d') . '.csv"');
            header('Content-Length: ' . filesize($this->csvFile));
            readfile($this->csvFile);
            exit;
        }
    }
    
    public function getLastModified() {
        if (file_exists($this->csvFile)) {
            return filemtime($this->csvFile);
        }
        return null;
    }
}
