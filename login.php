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

$algos = hash_hmac_algos(); // [5] => sha256, [9] => sha512, [11] => sha3-256

$valid_users = [
    // $algo$salt$hash
    'admin' => '$11$47aHeAuRQmMe95f/hYZts4CNDsIDJX6wSItnK9GyJG81$2c8d3574786e31fe060eedeadee67700e011093b5aaf2bbc944a27e0f987bb67',
 ];

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $passphrase = $_POST['password'] ?? '';
    
    // $data[1] = algorithm; $data[2] = salt; $data[3] = hash
    $data = explode('$', $valid_users[$username]);

    $password = hash_hmac($algos[$data[1]], $passphrase, $data[2]);

    if (isset($valid_users[$username]) && $password == $data[3]) {
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
