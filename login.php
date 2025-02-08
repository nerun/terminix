<?php
/*  terminal.php - version 1 - 2025-02-08
 *
 *  MIT License
 *
 *  Copyright (c) 2024, 2025 Daniel Dias Rodrigues <danieldiasr@gmail.com>.
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

define('LOGIN', 'login.tmp');
define('TIMEOUT', 900);
define('SHADOW', 'shadow.php');

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

// 'username' => '$algorithm$salt$hash',
$valid_users = [
    'admin' => '$11$47aHeAuRQmMe95f/hYZts4CNDsIDJX6wSItnK9GyJG81$2c8d3574786e31fe060eedeadee67700e011093b5aaf2bbc944a27e0f987bb67',
];

if (is_file(SHADOW)) {
    include(SHADOW);
}

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
    <meta name="author" content="Daniel Dias Rodrigues">
    <meta name="copyright" content="© 2024, 2025 Daniel Dias Rodrigues" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
    <link rel="icon" type="image/webp" href="terminix-ico.webp">
    <title>Terminix Login</title>
</head>
<body>
    <center>
        <h2 style="margin-top:80px">Terminix Login</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>\n"; ?>
        <?php if (!empty($expired)) echo "<p style='text-align:center; color:red;'>$expired</p>\n" ?>
        <form method="post">
            <label>Username: <input type="text" name="username" required autofocus></label><br><br>
            <label>Password: <input type="password" name="password" required></label><br><br>
            <button type="submit">Login</button>
        </form>
    </center>
</body>
</html>
