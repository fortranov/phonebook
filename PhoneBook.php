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
        
        $results = [];
        foreach ($this->data as $row) {
            foreach ($row as $cell) {
                if (stripos($cell, $query) !== false) {
                    $results[] = $row;
                    break;
                }
            }
        }
        return $results;
    }
    
    public function sortData($column, $direction = 'asc') {
        $data = $this->data;
        
        usort($data, function($a, $b) use ($column, $direction) {
            $valueA = isset($a[$column]) ? $a[$column] : '';
            $valueB = isset($b[$column]) ? $b[$column] : '';
            
            $result = strcasecmp($valueA, $valueB);
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
