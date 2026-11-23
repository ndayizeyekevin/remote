<?php
require_once __DIR__ . '/includes/require_admin.php';

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$allowedPageSizes = [10, 25, 50, 100];
$requestedPageSize = isset($_GET['per_page']) && is_string($_GET['per_page'])
    ? filter_var($_GET['per_page'], FILTER_VALIDATE_INT)
    : 25;
$pageSize = is_int($requestedPageSize) && in_array($requestedPageSize, $allowedPageSizes, true)
    ? $requestedPageSize
    : 25;
$page = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT)
    : 1;
if (!is_int($page) || $page < 1) {
    $page = 1;
}
$totalItems = 0;
$totalPages = 1;
$offset = 0;
$flash = adminConsumeFlash();

try {
    if ($search === '') {
        $countStmt = $db->query('SELECT COUNT(*) FROM menu');
    } else {
        $searchTerm = '%' . $search . '%';
        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM menu
             WHERE menu_name LIKE :search_name OR menu_desc LIKE :search_desc'
        );
        $countStmt->execute([':search_name' => $searchTerm, ':search_desc' => $searchTerm]);
    }
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalItems / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;

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
    $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $menuItems = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Unable to load menu items for admin: ' . $e->getMessage());
    http_response_code(500);
    $menuItems = [];
    $totalItems = 0;
    $totalPages = 1;
    $page = 1;
    $offset = 0;
    $flash = ['message' => 'Menu items could not be loaded.', 'type' => 'error'];
}
$firstItem = $totalItems > 0 ? $offset + 1 : 0;
$lastItem = $offset + count($menuItems);
$previousPageUrl = '?' . http_build_query([
    'q' => $search,
    'page' => max(1, $page - 1),
    'per_page' => $pageSize,
]);
$nextPageUrl = '?' . http_build_query([
    'q' => $search,
    'page' => min($totalPages, $page + 1),
    'per_page' => $pageSize,
]);

if ($totalPages <= 7) {
    $visiblePages = range(1, $totalPages);
} else {
    $visiblePages = [1];
    $windowStart = max(2, $page - 1);
    $windowEnd = min($totalPages - 1, $page + 1);
    if ($page <= 3) {
        $windowEnd = min($totalPages - 1, 4);
    } elseif ($page >= $totalPages - 2) {
        $windowStart = max(2, $totalPages - 3);
    }
    if ($windowStart > 2) {
        $visiblePages[] = '...';
    }
    for ($number = $windowStart; $number <= $windowEnd; $number++) {
        $visiblePages[] = $number;
    }
    if ($windowEnd < $totalPages - 1) {
        $visiblePages[] = '...';
    }
    $visiblePages[] = $totalPages;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu management | KABC Hotel</title>
    <link rel="icon" type="image/png" href="../assets/images/icon.png">
    <script src="../assets/js/admin_theme.js?v=2"></script>
    <link rel="stylesheet" href="../assets/css/admin.css?v=13">
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">
        <?php require __DIR__ . '/includes/header.php'; ?>
        <header class="admin-header">
            <div>
                <p class="eyebrow">MENU MANAGEMENT</p>
                <h1>Menu items</h1>
                <p class="muted">Search and edit item names, descriptions, and prices.</p>
            </div>
            <a class="button primary" href="menu_image_upload">Upload image</a>
        </header>

        <?php if ($flash): ?>
            <div class="notice <?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status"><?= adminEscape($flash['message']) ?></div>
        <?php endif; ?>

        <form class="search-form menu-search-form" id="menuSearchForm" method="get">
            <label class="visually-hidden" for="menuSearch">Search menu</label>
            <input id="menuSearch" name="q" type="search" value="<?= adminEscape($search) ?>" placeholder="Search by item or description">
            <button class="button primary" type="submit">Search</button>
            <a class="button" id="clearMenuSearch" href="menu"<?= $search === '' ? ' hidden' : '' ?>>Clear</a>
            <label class="menu-page-size" for="menuPageSize">
                <span>Rows per page</span>
                <select id="menuPageSize" name="per_page">
                    <?php foreach ($allowedPageSizes as $allowedPageSize): ?>
                        <option value="<?= $allowedPageSize ?>"<?= $pageSize === $allowedPageSize ? ' selected' : '' ?>><?= $allowedPageSize ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <p class="muted" id="menuSearchStatus" aria-live="polite">
            <?= $totalItems === 0 ? '0 items found.' : 'Showing ' . $firstItem . '-' . $lastItem . ' of ' . $totalItems . ' items.' ?>
        </p>

        <section class="admin-card table-wrap">
            <table class="admin-table">
                <thead><tr><th>Item</th><th>Description</th><th>Price</th><th>Image</th><th></th></tr></thead>
                <tbody id="menuResults">
                <?php foreach ($menuItems as $item): ?>
                    <tr>
                        <td><?= adminEscape($item['menu_name']) ?></td>
                        <td><?= adminEscape($item['menu_desc']) ?></td>
                        <td><?= number_format((float) $item['menu_price']) ?> RWF</td>
                        <td><?= !empty($item['image_path']) ? 'Available' : 'None' ?></td>
                        <td><a class="button" href="menu_edit?id=<?= (int) $item['menu_id'] ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$menuItems): ?><tr><td colspan="5" class="muted">No menu items found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
        <nav class="menu-pagination" id="menuPagination" aria-label="Menu results pages"<?= $totalPages <= 1 ? ' hidden' : '' ?>>
            <div class="menu-pagination-info">
                <span class="pagination-current" id="menuPageStatus" aria-live="polite">Page <?= $page ?> of <?= $totalPages ?></span>
            </div>
            <div class="menu-pagination-controls">
                <a class="button pagination-arrow" id="menuPreviousPage" data-page="<?= max(1, $page - 1) ?>"
                    href="<?= adminEscape($previousPageUrl) ?>" rel="prev"
                    aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>"<?= $page <= 1 ? ' tabindex="-1"' : '' ?>>Previous</a>
                <div class="menu-page-numbers" id="menuPageNumbers" aria-label="Select menu page">
                    <?php foreach ($visiblePages as $visiblePage): ?>
                        <?php if ($visiblePage === '...'): ?>
                            <span class="pagination-ellipsis" aria-hidden="true">...</span>
                        <?php else: ?>
                            <?php $pageUrl = '?' . http_build_query(['q' => $search, 'page' => $visiblePage, 'per_page' => $pageSize]); ?>
                            <a class="pagination-page<?= $visiblePage === $page ? ' is-current' : '' ?>"
                                href="<?= adminEscape($pageUrl) ?>" data-page="<?= $visiblePage ?>"
                                aria-label="Page <?= $visiblePage ?>"<?= $visiblePage === $page ? ' aria-current="page"' : '' ?>><?= $visiblePage ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <a class="button pagination-arrow" id="menuNextPage" data-page="<?= min($totalPages, $page + 1) ?>"
                    href="<?= adminEscape($nextPageUrl) ?>" rel="next"
                    aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>"<?= $page >= $totalPages ? ' tabindex="-1"' : '' ?>>Next</a>
            </div>
        </nav>
    </main>
</div>
<script src="../assets/js/admin_menu_search.js?v=3"></script>
</body>
</html>
