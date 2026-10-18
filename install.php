<?php
/**
 * Installation wizard.
 *
 * Run this once in a browser after uploading the site and creating an
 * empty MySQL/MariaDB database in cPanel. It will:
 *   1. Check server requirements
 *   2. Save your database and site details to config/config.php
 *   3. Import the database schema and club content
 *   4. Create your first administrator account
 *
 * When finished, it writes config/installed.lock and refuses to run
 * again. For extra safety, delete install.php from the server entirely
 * once setup is complete (see documentation/README.md).
 */

session_start();
define('APP_ROOT', __DIR__);

$configFile = APP_ROOT . '/config/config.php';
$lockFile = APP_ROOT . '/config/installed.lock';
$sampleConfig = APP_ROOT . '/config/config.sample.php';
$schemaFile = APP_ROOT . '/database/install.sql';

function ie(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Splits a block of SQL into individual statements on ';' boundaries,
 * WITHOUT splitting on semicolons that appear inside single-quoted string
 * literals (e.g. HTML entities like "&gt;" in demo content, which contain
 * a semicolon). A naive explode(';', $sql) breaks on those.
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $inString = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $current .= $char;

        if ($char === "'" && $inString) {
            // A doubled '' inside a string is an escaped literal quote, not the end of the string.
            if (($sql[$i + 1] ?? '') === "'") {
                $current .= "'";
                $i++;
                continue;
            }
            $inString = false;
        } elseif ($char === "'" && !$inString) {
            $inString = true;
        } elseif ($char === '\\' && $inString) {
            // Backslash-escapes the next character inside a string; consume it verbatim.
            if ($i + 1 < $length) {
                $current .= $sql[$i + 1];
                $i++;
            }
        } elseif ($char === ';' && !$inString) {
            $statements[] = substr($current, 0, -1);
            $current = '';
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}

function installer_csrf_token(): string
{
    if (empty($_SESSION['installer_csrf'])) {
        $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['installer_csrf'];
}

function installer_csrf_ok(): bool
{
    return !empty($_POST['csrf_token']) && !empty($_SESSION['installer_csrf'])
        && hash_equals($_SESSION['installer_csrf'], $_POST['csrf_token']);
}

function render_shell(string $title, string $content): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . ie($title) . ' — Installation</title>';
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    echo '<style>
        .installer-wrap{max-width:720px;margin:2.5rem auto;padding:0 1.25rem;}
        .installer-card{background:var(--color-surface,#fff);border-radius:var(--radius-lg,16px);padding:2rem;box-shadow:0 4px 24px rgba(0,0,0,.08);}
        .installer-steps{display:flex;gap:.5rem;margin-bottom:1.5rem;list-style:none;padding:0;flex-wrap:wrap;}
        .installer-steps li{padding:.35rem .8rem;border-radius:999px;background:#eee;font-size:.85rem;}
        .installer-steps li.active{background:var(--color-primary,#2f6d4f);color:#fff;font-weight:600;}
        .req-list{list-style:none;padding:0;}
        .req-list li{padding:.5rem 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;}
        .req-ok{color:#1a7a3c;font-weight:600;}
        .req-fail{color:#b3261e;font-weight:600;}
        form.installer-form label{display:block;margin:.9rem 0 .3rem;font-weight:600;}
        form.installer-form input{width:100%;padding:.6rem .75rem;border:1px solid #ccc;border-radius:8px;font-size:1rem;}
        form.installer-form small{color:#666;}
    </style></head><body>';
    echo '<div class="installer-wrap"><div class="installer-card">';
    echo '<h1>' . ie($title) . '</h1>';
    echo $content;
    echo '</div></div></body></html>';
}

// -------------------------------------------------------------------
// Guard: already installed
// -------------------------------------------------------------------
if (file_exists($lockFile)) {
    render_shell('Already Installed', '<p>This website has already been installed.</p>
        <p>If you genuinely need to run the installer again (for example, to set up a fresh copy of the site), delete
        <code>config/installed.lock</code> from the server first. Otherwise, go to your
        <a href="admin/login.php">admin login page</a>.</p>');
    exit;
}

$step = isset($_GET['step']) ? max(1, min(4, (int) $_GET['step'])) : 1;
$errors = [];

// If config already exists (step 2 previously completed), skip straight to step 3.
if ($step < 3 && file_exists($configFile)) {
    $step = 3;
}

// -------------------------------------------------------------------
// STEP 1 — Requirements check
// -------------------------------------------------------------------
if ($step === 1) {
    $checks = [
        'PHP version 8.0 or higher (found ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO MySQL extension' => extension_loaded('pdo_mysql'),
        'GD image extension (for thumbnails)' => extension_loaded('gd'),
        'fileinfo extension (for secure upload checks)' => extension_loaded('fileinfo'),
        'JSON extension' => extension_loaded('json'),
        '/config folder is writable' => is_writable(APP_ROOT . '/config'),
        '/uploads folder is writable' => is_writable(APP_ROOT . '/uploads'),
        '/logs folder is writable (will be created automatically)' => true,
    ];

    $allPass = !in_array(false, $checks, true);

    $html = '<ul class="installer-steps"><li class="active">1. Requirements</li><li>2. Database</li><li>3. Admin account</li><li>4. Finish</li></ul>';
    $html .= '<p>Before we begin, let\'s check your server meets the requirements.</p>';
    $html .= '<ul class="req-list">';
    foreach ($checks as $label => $pass) {
        $html .= '<li>' . ie($label) . ' <span class="' . ($pass ? 'req-ok">✓ OK' : 'req-fail">✕ Missing') . '</span></li>';
    }
    $html .= '</ul>';

    if ($allPass) {
        $html .= '<p><a class="btn btn--primary" href="install.php?step=2">Continue to Database Setup &raquo;</a></p>';
    } else {
        $html .= '<p><strong>Please fix the items marked "Missing" above</strong> (ask your hosting provider if you are unsure how), then reload this page.</p>';
        $html .= '<p><a href="install.php?step=1">Re-check requirements</a></p>';
    }

    render_shell('Step 1: Requirements Check', $html);
    exit;
}

// -------------------------------------------------------------------
// STEP 2 — Database + site details
// -------------------------------------------------------------------
if ($step === 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!installer_csrf_ok()) {
            $errors[] = 'Your session expired. Please try submitting the form again.';
        } else {
            $dbHost = trim($_POST['db_host'] ?? '');
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';
            $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
            $siteName = trim($_POST['site_name'] ?? '') ?: 'Nettlebed Village Club';
            $siteVillage = trim($_POST['site_village'] ?? '') ?: 'Nettlebed';
            $mailTo = trim($_POST['mail_to'] ?? '');

            if ($dbHost === '' || $dbName === '' || $dbUser === '') {
                $errors[] = 'Please fill in the database host, name and username.';
            }
            if ($siteUrl === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Please enter a valid site URL, e.g. https://www.example.com';
            }
            if ($mailTo === '' || !filter_var($mailTo, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address to receive contact form messages.';
            }

            $pdo = null;
            if (empty($errors)) {
                try {
                    $pdo = new PDO(
                        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                        $dbUser,
                        $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                } catch (PDOException $e) {
                    $errors[] = 'Could not connect to that database: ' . $e->getMessage()
                        . '. Make sure you have already created the database and user in cPanel, and that this user has full privileges on it.';
                }
            }

            if (empty($errors) && $pdo) {
                try {
                    $sql = file_get_contents($schemaFile);
                    $lines = explode("\n", $sql);
                    $clean = '';
                    foreach ($lines as $line) {
                        if (ltrim($line) === '' || str_starts_with(ltrim($line), '--')) {
                            continue;
                        }
                        $clean .= $line . "\n";
                    }
                    $statements = array_filter(array_map('trim', split_sql_statements($clean)));
                    foreach ($statements as $statement) {
                        $pdo->exec($statement);
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database connected, but importing the schema failed: ' . $e->getMessage();
                }
            }

            if (empty($errors)) {
                $appSecret = bin2hex(random_bytes(32));
                $config = "<?php\n"
                    . "// Generated automatically by install.php on " . date('Y-m-d H:i:s') . "\n"
                    . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                    . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                    . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                    . "define('DB_CHARSET', 'utf8mb4');\n\n"
                    . "define('SITE_NAME', " . var_export($siteName, true) . ");\n"
                    . "define('SITE_VILLAGE', " . var_export($siteVillage, true) . ");\n"
                    . "define('SITE_URL', " . var_export($siteUrl, true) . ");\n\n"
                    . "define('APP_SECRET', " . var_export($appSecret, true) . ");\n"
                    . "define('FORCE_HTTPS', " . (str_starts_with($siteUrl, 'https://') ? 'true' : 'false') . ");\n"
                    . "define('SESSION_IDLE_TIMEOUT_MINUTES', 20);\n\n"
                    . "define('MAIL_METHOD', 'mail');\n"
                    . "define('MAIL_FROM_ADDRESS', " . var_export('noreply@' . parse_url($siteUrl, PHP_URL_HOST), true) . ");\n"
                    . "define('MAIL_FROM_NAME', " . var_export($siteName, true) . ");\n"
                    . "define('MAIL_TO_ADDRESS', " . var_export($mailTo, true) . ");\n"
                    . "define('SMTP_HOST', '');\n"
                    . "define('SMTP_PORT', 587);\n"
                    . "define('SMTP_USERNAME', '');\n"
                    . "define('SMTP_PASSWORD', '');\n"
                    . "define('SMTP_ENCRYPTION', 'tls');\n\n"
                    . "define('MAX_UPLOAD_IMAGE_BYTES', 5 * 1024 * 1024);\n"
                    . "define('MAX_UPLOAD_PDF_BYTES', 15 * 1024 * 1024);\n"
                    . "define('MAX_UPLOAD_VIDEO_BYTES', 30 * 1024 * 1024);\n"
                    . "define('ALLOW_VIDEO_UPLOADS', false);\n\n"
                    . "define('APP_ENV', 'production');\n"
                    . "define('INSTALLER_UNLOCKED', false);\n";

                if (file_put_contents($configFile, $config) === false) {
                    $errors[] = 'Could not write config/config.php. Please check that the /config folder is writable and try again.';
                } else {
                    @chmod($configFile, 0640);
                    header('Location: install.php?step=3');
                    exit;
                }
            }
        }
    }

    $html = '<ul class="installer-steps"><li>1. Requirements</li><li class="active">2. Database</li><li>3. Admin account</li><li>4. Finish</li></ul>';

    foreach ($errors as $error) {
        $html .= '<p class="req-fail">' . ie($error) . '</p>';
    }

    $html .= '<p>Enter the database details from your cPanel "MySQL Databases" page. The database must already exist —
        create it there first if you have not already.</p>';
    $html .= '<form class="installer-form" method="post" action="install.php?step=2">';
    $html .= '<input type="hidden" name="csrf_token" value="' . ie(installer_csrf_token()) . '">';
    $html .= '<label for="db_host">Database host</label><input id="db_host" name="db_host" value="' . ie($_POST['db_host'] ?? 'localhost') . '" required>';
    $html .= '<label for="db_name">Database name</label><input id="db_name" name="db_name" value="' . ie($_POST['db_name'] ?? '') . '" required>';
    $html .= '<label for="db_user">Database username</label><input id="db_user" name="db_user" value="' . ie($_POST['db_user'] ?? '') . '" required>';
    $html .= '<label for="db_pass">Database password</label><input id="db_pass" type="password" name="db_pass" value="">';
    $html .= '<hr style="margin:1.5rem 0;">';
    $html .= '<label for="site_url">Full website address (URL)</label><input id="site_url" name="site_url" placeholder="https://www.example.com" value="' . ie($_POST['site_url'] ?? '') . '" required>';
    $html .= '<label for="site_name">Club name</label><input id="site_name" name="site_name" value="' . ie($_POST['site_name'] ?? 'Nettlebed Village Club') . '">';
    $html .= '<label for="site_village">Village name</label><input id="site_village" name="site_village" value="' . ie($_POST['site_village'] ?? 'Nettlebed') . '">';
    $html .= '<label for="mail_to">Email address for contact form messages</label><input id="mail_to" type="email" name="mail_to" value="' . ie($_POST['mail_to'] ?? '') . '" required>';
    $html .= '<p style="margin-top:1.5rem;"><button class="btn btn--primary" type="submit">Save & Import Database &raquo;</button></p>';
    $html .= '</form>';

    render_shell('Step 2: Database Setup', $html);
    exit;
}

// -------------------------------------------------------------------
// STEP 3 — Create first administrator account
// -------------------------------------------------------------------
if ($step === 3) {
    if (!file_exists($configFile)) {
        header('Location: install.php?step=2');
        exit;
    }
    require_once $configFile;
    require_once APP_ROOT . '/includes/db.php';

    // If an administrator already exists, this step is done — move on.
    try {
        $existing = db()->query("SELECT COUNT(*) AS c FROM admin_users WHERE role = 'administrator'")->fetch();
        if ((int) $existing['c'] > 0) {
            header('Location: install.php?step=4');
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = 'Could not read the admin_users table: ' . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        if (!installer_csrf_ok()) {
            $errors[] = 'Your session expired. Please try submitting the form again.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['password_confirm'] ?? '';

            if (!preg_match('/^[a-zA-Z0-9_.\-]{3,60}$/', $username)) {
                $errors[] = 'Username must be 3–60 characters and contain only letters, numbers, dots, dashes and underscores.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            if (mb_strlen($fullName) < 2) {
                $errors[] = 'Please enter your full name.';
            }
            if (mb_strlen($password) < 10) {
                $errors[] = 'Your password must be at least 10 characters long.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                try {
                    $stmt = db()->prepare('INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active, created_at)
                        VALUES (:u, :e, :p, :f, "administrator", 1, NOW())');
                    $stmt->execute([
                        'u' => $username,
                        'e' => $email,
                        'p' => password_hash($password, PASSWORD_DEFAULT),
                        'f' => $fullName,
                    ]);
                    header('Location: install.php?step=4');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = str_contains($e->getMessage(), 'Duplicate')
                        ? 'That username or email is already in use.'
                        : 'Could not create the account: ' . $e->getMessage();
                }
            }
        }
    }

    $html = '<ul class="installer-steps"><li>1. Requirements</li><li>2. Database</li><li class="active">3. Admin account</li><li>4. Finish</li></ul>';
    foreach ($errors as $error) {
        $html .= '<p class="req-fail">' . ie($error) . '</p>';
    }
    $html .= '<p>Create your first administrator account. This account will have full access to the admin area.</p>';
    $html .= '<form class="installer-form" method="post" action="install.php?step=3">';
    $html .= '<input type="hidden" name="csrf_token" value="' . ie(installer_csrf_token()) . '">';
    $html .= '<label for="full_name">Full name</label><input id="full_name" name="full_name" value="' . ie($_POST['full_name'] ?? '') . '" required>';
    $html .= '<label for="username">Username</label><input id="username" name="username" value="' . ie($_POST['username'] ?? '') . '" required>';
    $html .= '<label for="email">Email address</label><input id="email" type="email" name="email" value="' . ie($_POST['email'] ?? '') . '" required>';
    $html .= '<label for="password">Password</label><input id="password" type="password" name="password" required minlength="10">';
    $html .= '<small>At least 10 characters. Use a mix of letters, numbers and symbols.</small>';
    $html .= '<label for="password_confirm">Confirm password</label><input id="password_confirm" type="password" name="password_confirm" required minlength="10">';
    $html .= '<p style="margin-top:1.5rem;"><button class="btn btn--primary" type="submit">Create Administrator Account &raquo;</button></p>';
    $html .= '</form>';

    render_shell('Step 3: Administrator Account', $html);
    exit;
}

// -------------------------------------------------------------------
// STEP 4 — Finish
// -------------------------------------------------------------------
if ($step === 4) {
    if (!file_exists($configFile)) {
        header('Location: install.php?step=2');
        exit;
    }
    file_put_contents($lockFile, 'Installed on ' . date('Y-m-d H:i:s') . "\n");
    @chmod($lockFile, 0640);

    $html = '<ul class="installer-steps"><li>1. Requirements</li><li>2. Database</li><li>3. Admin account</li><li class="active">4. Finish</li></ul>';
    $html .= '<p><strong>Installation complete.</strong> Your website and admin account are ready.</p>';
    $html .= '<p><a class="btn btn--primary" href="admin/login.php">Go to Admin Login &raquo;</a> &nbsp; <a href="index.php">View the Website</a></p>';
    $html .= '<hr style="margin:1.5rem 0;">';
    $html .= '<p><strong>Important — before going live:</strong></p>';
    $html .= '<ul class="req-list">';
    $html .= '<li>Delete or rename <code>install.php</code> from the server (it now refuses to run again, but removing it entirely is safer).</li>';
    $html .= '<li>Replace all placeholder text and images — see <code>documentation/README.md</code> for a full checklist.</li>';
    $html .= '<li>Review Admin &gt; Settings for your real contact details, address, opening hours and social links.</li>';
    $html .= '<li>Ask your club to review the Privacy Policy and Cookie Policy pages before launch.</li>';
    $html .= '</ul>';

    render_shell('Installation Complete', $html);
    exit;
}
