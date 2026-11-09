<?php
/**
 * One-time admin account creator. COMMAND LINE ONLY (refuses to run in a browser).
 *
 *   cd path/to/maison-sucre/admin
 *   php setup_admin.php
 *
 * XAMPP on Windows:  C:\xampp\php\php.exe setup_admin.php
 *
 * It creates the `admins` table if needed, then asks for name, email and password.
 * Run it again with an existing admin's email to reset that admin's password.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/db.php';   // defines DB_HOST / DB_NAME / DB_USER / DB_PASS

/** Fresh connection each time: a connection left idle while you type can be dropped by MySQL ("server has gone away"). */
function db(): PDO
{
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admins (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(100) NOT NULL,
        email           VARCHAR(150) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        locked_until    INT UNSIGNED NOT NULL DEFAULT 0,
        last_login      DATETIME NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

function ask(string $label, bool $hidden = false): string
{
    echo $label;
    $canHide = $hidden && stripos(PHP_OS_FAMILY, 'Windows') === false && function_exists('shell_exec');
    if ($canHide) { shell_exec('stty -echo 2>/dev/null'); }
    $line = fgets(STDIN);
    if ($canHide) { shell_exec('stty echo 2>/dev/null'); echo "\n"; }
    return $line === false ? '' : rtrim($line, "\r\n");
}

echo "\nMaison Sucre - admin account setup\n";
if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    echo "(Note: on Windows the password is visible while you type. Close this window afterwards.)\n";
}
echo "\n";

$email = strtolower(trim(ask('Admin email: ')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    exit("That is not a valid email address.\n");
}

$pdo = db();
$exists = $pdo->prepare('SELECT id, name FROM admins WHERE email = ?');
$exists->execute([$email]);
$row = $exists->fetch();

$name = '';
if ($row) {
    if (strtolower(trim(ask("An admin with this email already exists ({$row['name']}). Reset the password? (y/N): "))) !== 'y') {
        exit("Nothing changed.\n");
    }
} else {
    $name = trim(ask('Full name: '));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        exit("Name must be 2 to 100 characters.\n");
    }
}

$pass = ask('Password (min 10 chars, letters + numbers): ', true);
$conf = ask('Confirm password: ', true);
if ($pass !== $conf) {
    exit("The passwords do not match.\n");
}
if (strlen($pass) < 10 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
    exit("Password must be at least 10 characters and contain a letter and a number.\n");
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo  = db();
if ($row) {
    $pdo->prepare('UPDATE admins SET password_hash = ?, failed_attempts = 0, locked_until = 0 WHERE id = ?')->execute([$hash, $row['id']]);
    echo "\nPassword updated for {$email}.\n";
} else {
    $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)')->execute([$name, $email, $hash]);
    echo "\nAdmin created. Log in at  /admin/login.php  with {$email}\n";
}