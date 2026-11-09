<?php
// PAGE 3 - Login + Registration (one page, two tabs)
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$next   = safe_next($_GET['next'] ?? ($_POST['next'] ?? ''));
$tab    = (($_GET['tab'] ?? '') === 'register') ? 'register' : 'login';
$errors = [];
$old    = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    /* ================= REGISTER ================= */
    if ($action === 'register') {
        $tab   = 'register';
        $name  = post_str('name');
        $email = strtolower(post_str('email'));
        $phone = preg_replace('/\D/', '', post_str('phone'));
        $pass  = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $conf  = isset($_POST['confirm'])  && is_string($_POST['confirm'])  ? $_POST['confirm']  : '';
        $old   = ['name' => $name, 'email' => $email, 'phone' => $phone];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Please enter your name (2 to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number.';
        }
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
        }
        if ($pass !== $conf) {
            $errors[] = 'The two passwords do not match.';
        }

        if (!$errors) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'An account with this email already exists. Try logging in instead.';
            }
        }

        if (!$errors) {
            try {
                $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)');
                $ins->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone]);
                flash('success', 'Account created! You can log in now.');
                redirect('auth.php?next=' . urlencode($next));
            } catch (PDOException $ex) {
                // 23000 = duplicate email slipped in between our check and the insert
                $errors[] = ($ex->getCode() === '23000')
                    ? 'An account with this email already exists.'
                    : 'Could not create your account. Please try again.';
            }
        }
    }

    /* ================= LOGIN ================= */
    elseif ($action === 'login') {
        $tab   = 'login';
        $email = strtolower(post_str('email'));
        $pass  = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $old['email'] = $email;

        // Simple brute-force guard: 5 wrong tries -> wait 60 seconds
        if (($_SESSION['login_fails'] ?? 0) >= 5 && time() < ($_SESSION['login_locked_until'] ?? 0)) {
            $errors[] = 'Too many failed attempts. Please wait a minute and try again.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password_hash'])) {
                session_regenerate_id(true);          // new session ID after login (stops session fixation)
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
                flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
                redirect($next);
            }

            $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 60;
            }
            $errors[] = 'Incorrect email or password.';
        }
    }
}

$pageTitle = 'Login / Register';
$active    = 'auth';
require __DIR__ . '/includes/header.php';
?>

<section class="container section">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-6">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 text-center mb-1">Welcome to <?= e(SHOP_NAME) ?></h1>
                    <p class="text-center text-muted small mb-4">Log in to order, or create an account in a minute.</p>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-pills nav-fill auth-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $tab === 'login' ? 'active' : '' ?>" data-bs-toggle="pill"
                                    data-bs-target="#pane-login" type="button" role="tab">Login</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $tab === 'register' ? 'active' : '' ?>" data-bs-toggle="pill"
                                    data-bs-target="#pane-register" type="button" role="tab">Register</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Login -->
                        <div class="tab-pane fade <?= $tab === 'login' ? 'show active' : '' ?>" id="pane-login" role="tabpanel">
                            <form method="post" action="auth.php" class="needs-validation" novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="next" value="<?= e($next) ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="l-email">Email</label>
                                    <input type="email" class="form-control" id="l-email" name="email" required
                                           value="<?= e($tab === 'login' ? $old['email'] : '') ?>" autocomplete="email">
                                    <div class="invalid-feedback">Enter a valid email.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="l-pass">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="l-pass" name="password" required autocomplete="current-password">
                                        <button class="btn btn-outline-rose toggle-pw" type="button" data-target="l-pass">Show</button>
                                        <div class="invalid-feedback">Enter your password.</div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-rose w-100 btn-lg">Log in</button>
                            </form>
                        </div>

                        <!-- Register -->
                        <div class="tab-pane fade <?= $tab === 'register' ? 'show active' : '' ?>" id="pane-register" role="tabpanel">
                            <form method="post" action="auth.php" class="needs-validation" novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="register">
                                <input type="hidden" name="next" value="<?= e($next) ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="r-name">Full name</label>
                                    <input type="text" class="form-control" id="r-name" name="name" required minlength="2" maxlength="100"
                                           value="<?= e($tab === 'register' ? $old['name'] : '') ?>" autocomplete="name">
                                    <div class="invalid-feedback">Please enter your name.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="r-email">Email</label>
                                    <input type="email" class="form-control" id="r-email" name="email" required maxlength="150"
                                           value="<?= e($tab === 'register' ? $old['email'] : '') ?>" autocomplete="email">
                                    <div class="invalid-feedback">Enter a valid email.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="r-phone">Mobile number</label>
                                    <input type="tel" class="form-control" id="r-phone" name="phone" required
                                           pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                                           value="<?= e($tab === 'register' ? $old['phone'] : '') ?>" autocomplete="tel">
                                    <div class="invalid-feedback">Enter a 10-digit mobile number.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="r-pass">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="r-pass" name="password" required minlength="8"
                                               pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password">
                                        <button class="btn btn-outline-rose toggle-pw" type="button" data-target="r-pass">Show</button>
                                        <div class="invalid-feedback">At least 8 characters, with a letter and a number.</div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="r-confirm">Confirm password</label>
                                    <input type="password" class="form-control" id="r-confirm" name="confirm" required autocomplete="new-password">
                                    <div class="invalid-feedback">The passwords must match.</div>
                                </div>
                                <button type="submit" class="btn btn-rose w-100 btn-lg">Create account</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
