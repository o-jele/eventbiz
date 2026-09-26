<?php
// Web installer. DELETE THIS FILE after a successful install.
declare(strict_types=1);

require __DIR__ . '/app/helpers.php';

$cfgFile = __DIR__ . '/config.php';
if (is_file($cfgFile)) {
    http_response_code(403);
    exit('Already installed (config.php exists). Delete install.php now.');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = (string) ($_POST['db_pass'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    try {
        if ($name === '' || $user === '' || $adminName === '' || $adminEmail === '' || strlen($adminPass) < 8) {
            throw new RuntimeException('Fill every field; admin password must be 8+ characters.');
        }
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        foreach (['schema.sql', 'seed.sql'] as $f) {
            $sql = file_get_contents(__DIR__ . '/app/database/' . $f);
            if ($sql === false) {
                throw new RuntimeException("Missing app/database/$f.");
            }
            $pdo->exec($sql);
        }
        $st = $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (?,?,?)');
        $st->execute([$adminName, '', $adminEmail]);
        $cid = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, customer_id) VALUES (?,?,?,?,?)');
        $st->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'admin', $cid]);
        $cfg = "<?php\nreturn [\n"
            . "    'db_host' => " . var_export($host, true) . ",\n"
            . "    'db_name' => " . var_export($name, true) . ",\n"
            . "    'db_user' => " . var_export($user, true) . ",\n"
            . "    'db_pass' => " . var_export($pass, true) . ",\n"
            . "    'site_name' => 'Glamorous',\n"
            . "    'upload_dir' => __DIR__ . '/uploads',\n];\n";
        file_put_contents($cfgFile, $cfg);
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }
        file_put_contents(__DIR__ . '/uploads/index.html', 'Not browsable.');
        echo '<h1>Installed.</h1><p><strong>Delete install.php now</strong> (and this message), then <a href="/login">log in</a>.</p>';
        exit;
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Glamorous installer</title></head><body>
<h1>Glamorous installer</h1>
<p>Needs: PHP 8.1+, MySQL/MariaDB, a database + user (create them in HestiaCP first).</p>
<?php if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><?php endif; ?>
<form method="post">
<p>DB host <input name="db_host" value="localhost"></p>
<p>DB name <input name="db_name" required></p>
<p>DB user <input name="db_user" required></p>
<p>DB password <input name="db_pass" type="password"></p>
<p>Admin name <input name="admin_name" required></p>
<p>Admin email <input name="admin_email" type="email" required></p>
<p>Admin password (8+ chars) <input name="admin_pass" type="password" required></p>
<p><button>Install</button></p>
</form>
</body></html>
