<?php
define('LOGIN', 'login.tmp');
define('TIMEOUT', 900);

$login = trim(file_get_contents(LOGIN));

function login_status($status){
    global $login;
    file_put_contents(LOGIN, $status);
    $login = trim(file_get_contents(LOGIN));
}

if (!is_file(LOGIN) || empty($login)) {
    login_status('off');
}

if ($login === 'off') {
    // continue...
} elseif ($login === 'expired') {
    $expired = "Your connection time has expired.";
    login_status('off');
} elseif (preg_match('/^on:(\d+)$/', $login, $matches)) {
    $unixtime = (int) $matches[1];
    if (time() - $unixtime <= TIMEOUT){
        header("Location: terminal.php");
        exit;
    } else {
        login_status('expired');
        header("Refresh: 0");
    }
} else {
    login_status('off');
    header("Refresh: 0");
}

// Simulação de credenciais com senha hash (use password_hash() para gerar)
$valid_users = [
    'daniel' => 'c58a9dd28d6a89216ed14d2ac156b658aca74449c879d79243bf0dea91757863', // hash de GURPZine MediaWiki em Bitwarden
 ];

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $salt = '$2y$10$6cf3366c7f7aafd72be4d6918a80bf7b079ec99d355c';
    $password = hash_hmac('sha3-256', $password, $salt);

    if (isset($valid_users[$username]) && $password == $valid_users[$username]) {
        $unix_time = time();
        login_status("on:$unix_time");
        header("Location: terminal.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <center>
        <h2>Terminix Login</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>\n"; ?>
        <?php if (!empty($expired)) echo "<p style='text-align:center; color:red;'>$expired</p>\n" ?>
        <form method="post">
            <label>Username: <input type="text" name="username" required></label><br><br>
            <label>Password: <input type="password" name="password" required></label><br><br>
            <button type="submit">Login</button>
        </form>
    </center>
</body>
</html>
