<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$rows = db()->query('SELECT * FROM gift_catalog ORDER BY sort_order ASC, id ASC')->fetchAll();
json_response(['gifts' => array_map(static function(array $row): array {
    return [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'icon' => (string)$row['icon'],
        'price' => (int)$row['price'],
    ];
}, $rows)]);
