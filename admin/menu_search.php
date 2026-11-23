<?php
require_once __DIR__ . '/includes/require_admin.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$limit = 25;
$page = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT)
    : 1;
if (!is_int($page) || $page < 1) {
    $page = 1;
}
$searchTerm = '%' . $search . '%';

try {
    if ($search === '') {
        $countStmt = $db->query('SELECT COUNT(*) FROM menu');
    } else {
        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM menu
             WHERE menu_name LIKE :count_name OR menu_desc LIKE :count_desc'
        );
        $countStmt->execute([':count_name' => $searchTerm, ':count_desc' => $searchTerm]);
    }
    $total = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $limit));
    $page = min($page, $pages);
    $offset = ($page - 1) * $limit;

    $query = 'SELECT menu_id, menu_name, menu_desc, menu_price, image_path FROM menu';
    if ($search !== '') {
        $query .= ' WHERE menu_name LIKE :search_name OR menu_desc LIKE :search_desc';
    }
    $query .= ' ORDER BY menu_name ASC, menu_id ASC LIMIT :page_size OFFSET :offset';
    $stmt = $db->prepare($query);
    if ($search !== '') {
        $stmt->bindValue(':search_name', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search_desc', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->bindValue(':page_size', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $item) {
        $items[] = [
            'id' => (int) $item['menu_id'],
            'name' => (string) $item['menu_name'],
            'description' => (string) $item['menu_desc'],
            'price' => number_format((float) $item['menu_price']),
            'has_image' => !empty($item['image_path']),
        ];
    }
} catch (PDOException $e) {
    error_log('Unable to search menu items for admin: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Menu items could not be searched. Please try again.']);
    exit;
}

echo json_encode([
    'items' => $items,
    'total' => $total,
    'limit' => $limit,
    'page' => $page,
    'pages' => $pages,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
