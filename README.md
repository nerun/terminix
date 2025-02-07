# Terminix - mini PHP terminal emulator

Terminix is ​​a mini PHP terminal emulator. It was created to overcome the lack of a Linux terminal on low-cost servers (usually collective). It provides basic commands for manipulating files and directories, as well as decompression.

![Terminix](screenshot.png)

## Commands available

Classic ones:
 * cd, clear, cp, ls, mkdir, mv, pwd, rm, rmdir
 
Terminix specific:
 * about - copyright and license information
 * help (or ?) - general help on commands
 * unzap - unarchiver and decompressor (bzip2, gz, rar, tar, zip)
 
## Installation

Download [all the files](https://github.com/nerun/terminix/zipball/main). You won't need `README.md` or `screenshot.png`, but the rest certainly will. Especially `.htaccess`, for your security.

Now install all files in any folder on your server (do not forget `.htaccess`, it's a hidden file!).

Assuming you have installed the files in the `/terminal` folder on your server, then to access the terminal, go to `login.php`:

    https://www.yoursite.com/terminal/login.php

Defaults:
 - username: `admin`
 - password: `123456`

## Change users, passwords and salt

Open file `login.php`, and search for `$valid_users` and `$salt`:

```php
$valid_users = [
    'admin' => '83a15c51d38269306b790f31f9d300489bc93f426868e543dc00cb11129780ba', // hash of '123456'
 ];
```

```php
$salt = '$2y$10$6cf3366c7f7aafd72be4d6918a80bf7b079ec99d355c';
```

You need to change username "admin" and hash. If you want more security change `$salt` too.

To gererate a new hash for a new password, using default `$salt`, use this script in a site like https://onlinephp.io, changing `$password`, of course:

```php
<?php
$password = '123456';
$salt = '$2y$10$6cf3366c7f7aafd72be4d6918a80bf7b079ec99d355c';
$hash = hash_hmac('sha3-256', $password, $salt);
echo "Hash for your new password: $hash\n";
```

To use a new different salt, use this instead:

```php
<?php
$password = '123456';
$salt = base64_encode(random_bytes(33));
$hash = hash_hmac('sha3-256', $password, $salt);
echo "Hash for your new password: $hash\n";
echo "Update $salt in login.php with: $salt\n"
```

## Change hash function

For a list of hash algorithms:

```php
print_r(hash_hmac_algos());
```

Change algorithm in `login.php`:

```php
$password = hash_hmac('sha3-256', $password, $salt);
```

## Change session timeout

Default session timeout is 15 minutes (900 seconds), you must change in both `login.php` and `terminal.php`:

```php
define('TIMEOUT', 900);
```