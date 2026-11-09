<?php
// Admin login. Uses its own `admins` table, so customer accounts can never become admins.
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    a_redirect('index.php');
}

const MAX_FAILS   = 5;      // wrong passwords before the account is locked
const LOCK_SECONDS = 300;   // lock length: 5 minutes

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    a_csrf_verify();
    $email = strtolower(a_str('email'));
    $pass  = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    try {
        $st = $pdo->prepare('SELECT id, name, password_hash, failed_attempts, locked_until FROM admins WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();

        if ($row && (int)$row['locked_until'] > time()) {
            $errors[] = 'This account is temporarily locked after too many wrong passwords. Try again in a few minutes.';
        } else {
            // Always run password_verify (against a throwaway hash if the email is unknown)
            // so response time does not reveal which emails are admins.
            $hash = $row['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
            $ok   = password_verify($pass, $hash) && $row;

            if ($ok) {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
                }
                $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = 0, last_login = NOW() WHERE id = ?')
                    ->execute([$row['id']]);

                session_regenerate_id(true);              // new session ID after login (stops session fixation)
                $_SESSION['admin_id']   = (int)$row['id'];
                $_SESSION['admin_last'] = time();
                a_flash('success', 'Welcome back, ' . explode(' ', $row['name'])[0] . '!');
                a_redirect('index.php');
            }

            if ($row) {
                $fails = (int)$row['failed_attempts'] + 1;
                if ($fails >= MAX_FAILS) {
                    $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = ? WHERE id = ?')
                        ->execute([time() + LOCK_SECONDS, $row['id']]);
                } else {
                    $pdo->prepare('UPDATE admins SET failed_attempts = ? WHERE id = ?')->execute([$fails, $row['id']]);
                }
            }
            $errors[] = 'Incorrect email or password.';
        }
    } catch (PDOException $ex) {
        $errors[] = 'Admin login is not set up yet. Run setup_admin.php once from the command line (see INSTALL.md).';
    }
}

a_header('Admin login');
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5 col-xl-4">
        <div class="card mt-4">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="fs-1">🥐</div>
                    <h1 class="h4 mb-0">Maison Sucre</h1>
                    <div class="text-muted small">Staff sign in</div>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2"><?php foreach ($errors as $err): ?><div><?= a_e($err) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="post" action="login.php" autocomplete="on">
                    <?= a_csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" required maxlength="150"
                               value="<?= a_e($email) ?>" autocomplete="username" autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-rose w-100" type="submit">Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center small mt-3"><a href="../index.php" class="text-muted">&larr; Back to the shop</a></p>
    </div>
</div>
<?php a_footer(); ?>
