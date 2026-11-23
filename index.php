<?php
// Load database credentials if uploaded to the server
$dbStatus = "Not tested (config.php not uploaded yet)";

if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $dbStatus = "✅ Connected successfully to MySQL!";
    } catch (PDOException $e) {
        $dbStatus = "❌ Connection failed." . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deployment Test</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 50px auto; line-height: 1.6; }
        .box { padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🚀 GitHub Auto-Deploy Test (v1.0)</h2>
        <p><strong>PHP Version:</strong> <?= phpversion(); ?></p>
        <p><strong>Database Status:</strong> <?= htmlspecialchars($dbStatus); ?></p>
        <p><strong>Server Time:</strong> <?= date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
