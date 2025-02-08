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
define('BUTTON1', 'https://github.com/nerun/terminix');
define('BUTTON1_LABEL', 'Back');

$login = trim(file_get_contents(LOGIN));

function login_status($status){
    global $login;
    file_put_contents(LOGIN, $status);
    $login = trim(file_get_contents(LOGIN));
}

if (!is_file(LOGIN) || empty($login)) {
    login_status('off');
}

if (preg_match('/^on:(\d+)$/', $login, $matches)) {
    $unixtime = (int) $matches[1];
    if (time() - $unixtime > TIMEOUT){
        login_status('expired');
        header("Location: login.php");
    }
} else {
    login_status('off');
    header("Location: login.php");
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
        <style>
            input, pre, .terminal {
                font-family: monospace;
                font-size: 11.25pt;
            }
            pre {
                margin: 0px;
            }
            .terminal {
                width: 737px;
                height: 443px;
                padding: 10px;
                margin-top: 0px;
                margin-right: auto;
                margin-bottom: 5px;
                margin-left: auto;
                border: 1px solid black;
                resize: both;
                overflow: auto;
            }
            a {
                text-decoration: none;
                color: gray;
            }
            a:hover {
                text-decoration: underline;
            }
        </style>
        <title>Terminix - PHP Terminal</title>
    </head>
    
    <body>
        <nav>
            <p align="center">
                <button type="button"
                    onclick="location.href='<?php echo BUTTON1; ?>';">
                        <?php echo BUTTON1_LABEL; ?>
                </button>
            </p>
        </nav>
    
        <header>
            <h2 align="center">Mini Terminal Emulator in PHP</h2>
        </header>
        
        <main>
            <form action="<?php htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" onSubmit="window.location.reload()">
                <div class="terminal">
                    <?php
                        echo "<script type=\"text/javascript\">
                        function bottom() {
                            document.getElementById('bottom').scrollIntoView();
                        };
                    </script>\n";

                        include('./terminal_bin.php');
                        include('./terminal_bin_unzap.php');

                        // Reset log every 18 hours (1h = 3600)
                        $timediff = time() - filemtime(LOGFILE);
                        if ( $timediff >= 64800 ){
                            file_put_contents(LOGFILE, null);
                        }

                        readfile(LOGFILE);
                        
                        echo "\t\t\t\t\t<div id=\"bottom\"></div>\n";
                        echo "\t\t\t\t\t<script type=\"text/javascript\">bottom()</script>\n";

                        if (isset($_POST['command'])) {
                            // log command
                            $input = trim($_POST['command']);
                            _log("$ $input");

                            // Creates an array from string using space as delimiter
                            $command = preg_split('/(?<!\\\\)\s+/', $input);
                            $command = str_replace('\ ', ' ', $command);
                            
                            // Commands existing in the php files included above
                            $validCommands = array('about', 'cd', 'clear', 'cp', 'help', '?', 'ls',
                                                   'mkdir', 'mv', 'pwd', 'rm', 'rmdir', 'unzap');
                            
                            if (in_array($command[0], $validCommands)) {
                                switch($command[0]){
                                    case '?':
                                        $command[0] = 'help';
                                        break;
                                    case 'rmdir':
                                        $command[0] = 'remdir';
                                        break;
                                    case 'mkdir':
                                        $command[0] = 'mkdirRecursive';
                                        break;
                                }

                                // Call the $command[0] function and pass subarray as arguments
                                // subarray = array $command minus 1st element
                                call_user_func($command[0], array_slice($command,1));
                            } else {
                                _log('command not found: '.$command[0]);
                            }

                            echo "<meta http-equiv='refresh' content='0'>";
                        }
                    ?>
                </div>

                <center>
                    <label for="command"><code>$</code></label>
                    <input type="text" name="command" size="74"
                        onfocus="this.value=''" autofocus
                        placeholder="Type 'help' or '?' for a list of commands." />
                    <button type="subscribe">ENTER</button>
                </center>
            </form>
            <p style="text-align:center">
                <a href="https://github.com/nerun/terminix" target="_blank" rel="noopener noreferrer">
                    Terminix 1.0
                </a>
            </p>
        </main>
    </body>
</html>
