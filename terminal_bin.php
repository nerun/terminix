<?php
/* terminal_bin.php - version 1 - 2025-02-08
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

date_default_timezone_set('UTC');

/* PHP magic constant:
 * __DIR__ = ROOT/terminal
 * (if Terminix is installed in 'terminal' folder, under public_html)
 */
define('ROOT', realpath($_SERVER['DOCUMENT_ROOT'])); // '/path/on/server/to/public_html'
define('ROOT_PATTERN', '/' . preg_replace('/\//i', '\\/', ROOT) . '/i');
define('LOGFILE', __DIR__ . '/terminal.log');
define('CWDFILE', __DIR__ . '/terminal.cwd');

$currentDir = trim(file_get_contents(CWDFILE));
if (empty($currentDir) || !is_dir($currentDir)) {
    $currentDir = ROOT;
    file_put_contents(CWDFILE, $currentDir);
}

define('CWD', $currentDir);
unset($currentDir);

////////////////////////////////////////////////////////////////////////////////

function _log($log, $mode="a") {
    // Mode "a" open a file for write only. The existing data in file is preserved.
    $logfile = fopen(LOGFILE, $mode) or die("Unable to open file " . LOGFILE);
    flock($logfile, LOCK_EX);
    //$lines = count(file(LOGFILE));
    fwrite($logfile, $log . "<br>\n");
    fclose($logfile);
    return true;
}

function _perms($perms){
    $info = match ($perms & 0xF000) {
        0xC000 => 's', // socket
        0xA000 => 'l', // symbolic link
        0x8000 => '-', // regular file
        0x6000 => 'b', // block special
        0x4000 => 'd', // directory
        0x2000 => 'c', // character special
        0x1000 => 'p', // FIFO pipe
        default => 'u', // unknown
    };
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;
}

function GetDirectorySize($path){
    $bytestotal = 0;
    $path = realpath($path);
    if($path!==false && $path!='' && file_exists($path)){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
            $bytestotal += filesize($object);
        }
    }
    return $bytestotal;
}

function normalizePath($path) {
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    $resolvedParts = [];

    foreach ($parts as $part) {
        if ($part === '..') {
            // Go up one level: remove the last valid directory, if it exists
            array_pop($resolvedParts);
        } elseif ($part !== '' && $part !== '.') {
            // Adds valid directories, ignoring '.'
            $resolvedParts[] = $part;
        }
    }

    return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $resolvedParts);
}

function getExistingPath($path) {
    $path = normalizePath($path); // Normalizes the path to resolve '..'
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    $existingPath = DIRECTORY_SEPARATOR;

    // Progressively checks subpaths
    foreach ($parts as $part) {
        $testPath = rtrim($existingPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;

        if (is_dir($testPath)) {
            $existingPath = $testPath;
        } else {
            break;
        }
    }

    return rtrim($existingPath, DIRECTORY_SEPARATOR);
}

function truepath($path){
    // Absolute path, resolves from ROOT
    // Relative path, resolves from current directory
    $tPath = ($path[0] == DIRECTORY_SEPARATOR) ? ROOT : CWD;
    $tPath .= DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    $tPath = normalizePath($tPath);
    return $tPath;
}

/******************************************************************************
 * LS                                                                         *
 ******************************************************************************/
function ls($args){
    $folders = array();
    
    if ( empty($args) ){
        $folders['.'] = CWD;
    } else {
        foreach ( $args as $folder_to_scan ){
            $folders[$folder_to_scan] = CWD . DIRECTORY_SEPARATOR . $folder_to_scan;
        }
    }
    
    function _perm_date($path, $format, $item_size){
        //$octal = decoct(fileperms("$path") & 0777);
        $size = sprintf("$format", $item_size);
        $size = preg_replace('/ /', '&ensp;', $size);
        $perm = '<span style="color:#8b008b">' . str_replace('0  ', '000  ', _perms(fileperms("$path"))) . '</span>';
        $date = '<span style="color:green">' . date("M d Y H:i", filemtime("$path")) . '</span>';
        return $perm . '&ensp;&ensp;' . $size . '&ensp;&ensp;' . $date . '&ensp;&ensp;';
    }
    
    foreach ( $folders as $key=>$value ){
        $folder = $value;
        
        $real_folder = realpath($folder);
        
        if ( !empty($real_folder) && strlen($real_folder) < strlen(realpath(ROOT)) ) {
            // Do not go above ROOT!
            $folder = ROOT;
        }
        
        if( count($folders) > 1 ){
            _log("$key:");
        }
        
        if ( !is_dir($folder) ){
            if ( is_file($folder) ){
                $file = substr($folder, strrpos($folder, DIRECTORY_SEPARATOR) + 1);
                $file_s = number_format(filesize($folder));
                $format = "%" . strlen($file_s) . "s";
                $perm_date = _perm_date("$folder", $format, $file_s);
                _log($perm_date . $file);
            } else {
                _log("ls: cannot access '$key' : No such file or directory");
            }
        } else {
            $scanned = array_diff(scandir($folder), array('..', '.'));
            natcasesort($scanned);

            $siz = GetDirectorySize($folder);
    
            _log('total: ' . number_format($siz) . ' bytes');
            
            $file_sizes = array();
            foreach ($scanned as $item){
                $item_size = number_format(filesize("$folder/$item"));
                $file_sizes["$item"] = $item_size;
            }
            
            $longest_siz = 0;
            foreach($file_sizes as $fs_key => $fs_value){
                if ( strlen($fs_value) > $longest_siz ){
                    $longest_siz = strlen($fs_value);
                }
            }
            $format = "%" . $longest_siz . "s";
            
            // --group-directories-first
            foreach ($scanned as $item){
                if ( is_dir("$folder/$item") == true ){
                    // &#128447; = 🖿
                    $perm_date = _perm_date("$folder/$item", $format, $file_sizes["$item"]);
                    _log($perm_date . '<span style="color:#0067a5;">&#128447; ' . $item . '</span>');
                }
            }
            
            // list files
            foreach ($scanned as $item){
                if ( is_dir("$folder/$item") == false ){
                    $perm_date = _perm_date("$folder/$item", $format, $file_sizes["$item"]);
                    _log($perm_date . $item);
                }
            }
        }
        
        if( count($folders) > 1 && $key !== array_key_last($folders) ){
            _log('');
        }
    }
}

/******************************************************************************
 * CLEAR                                                                      *
 ******************************************************************************/
function clear($args){
    if ($args){
        _log("clear: too many arguments");
        return false;
    } else{
        file_put_contents(LOGFILE, null);
    }

    return true;
}

/******************************************************************************
 * PWD                                                                        *
 ******************************************************************************/
function pwd($args){
    if ($args) {
        _log("pwd: too many arguments");
        return false;
    }
    
    $pwd = preg_replace(ROOT_PATTERN, '', file_get_contents(CWDFILE));
    
    if(empty($pwd)){
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $pwd = substr(ROOT, 0, 3);  // "C:\"
        } else {
            // Unix-like
            $pwd = DIRECTORY_SEPARATOR; // "/"
        }
    }
    
    _log($pwd . PHP_EOL);
    
    return true;
}

/******************************************************************************
 * CD                                                                         *
 ******************************************************************************/
function cd($path){
    if ( count($path) > 1 ) {
        _log("cd: too many arguments");
        return false;
    }

    $path = $path[0];

    $newPath = realpath(truepath($path));
    
    if ($newPath === false || (!is_dir($newPath) && !is_file($newPath))) {
        _log("cd: no such directory: $path");
        return false;
    }

    if (is_file($newPath)) {
        _log("cd: is not a directory: $path");
        return false;
    }

    if (strpos($newPath, ROOT) !== 0) {
        //_log("cd: cannot move above ROOT");
        $newPath = ROOT;
    }

    file_put_contents(CWDFILE, $newPath);
    
    return true;
}

/******************************************************************************
 * MKDIR                                                                      *
 ******************************************************************************/
function mkdirRecursive($dirs) {
    foreach ($dirs as $dir) {
        $newDir = truepath($dir);

        /* Since $newDir does not exist (it is the directory to be created),
         * we have to extract the existing path from the new path, then
         * check if the new directory is above ROOT.
         */
        $existingPath = getExistingPath($newDir);

        if (strpos($existingPath, ROOT) !== 0) {
            _log("mkdir: cannot create directory outside root: '$dir'.");
            continue;
        }

        // Attempt to create the directory recursively
        if (!@mkdir($newDir, 0755, true)) {
            if (is_dir($newDir)) {
                _log("mkdir: '$dir' already exists.");
            } else {
                _log("Error: mkdir: failed to create '$dir'. Check permissions or path.");
            }
        } else {
            _log("mkdir: created '$dir' successfully.");
        }
    }
}

/******************************************************************************
 * RMDIR                                                                      *
 ******************************************************************************/
function remdir($dirs) {
    foreach ($dirs as $dir) {
        $newDir = truepath($dir);
        
        $existingPath = getExistingPath($newDir);

        if (strpos($existingPath, ROOT) !== 0) {
            _log("rmdir: cannot remove directory outside root: '$dir'.");
            continue;
        }
        
        // Check if the path is a directory
        if (!is_dir($newDir)) {
            _log("rmdir: failed to remove '$dir': No such file or directory");
            continue;
        }

        // Check if the directory is empty
        if (count(scandir($newDir)) <= 2) {
            // Attempt to remove the empty directory
            if (rmdir($newDir)) {
                _log("removed empty directory '$dir'");
            } else {
                _log("Error: rmdir: unable to remove empty directory '$dir'.");
            }
        } else {
            _log("Error: rmdir: '$dir' is not empty.");
        }
    }
}

/******************************************************************************
 * RM                                                                         *
 ******************************************************************************/
function rm($files) {
    $success = true;
    
    // Check if '-r' is present anywhere in the array
    $recursive = in_array('-r', $files, true);
    
    // If found, remove it from the array
    if ($recursive) {
        $files = array_values(array_diff($files, ['-r']));
    }

    // Iterate over the array of files
    foreach ($files as $file) {
        $absFile = truepath($file);
        
        $existingPath = getExistingPath($absFile);

        if (strpos($existingPath, ROOT) !== 0) {
            _log("rm: cannot remove directory outside root: '$file'.");
            continue;
        }
        
        // Check if the file or directory exists
        if (!file_exists($absFile)) {
            _log("rm: could not remove '$file': No such file or directory");
            $success = false;
            continue;
        }

        // Check if the file is writable (can be deleted)
        if (!is_writable($absFile)) {
            _log("Error: rm: you do not have permission to delete '$file'.");
            $success = false;
            continue;
        }

        // If it's a directory and recursive removal is enabled, delete it recursively
        if (is_dir($absFile)) {
            if ($recursive) {
                // Delete directory and its contents recursively
                if (delete_directory_recursive($absFile)) {
                    _log("removed '$file'");
                } else {
                    _log("Error: rm: unable to delete '$file'.");
                    $success = false;
                }
            } else {
                _log("rm: could not remove '$file': It is a directory");
                _log("rm: use option '-r' to remove it recursively.");
                $success = false;
            }
        } else {
            // Attempt to delete the file
            if (unlink($absFile)) {
                _log("removed '$file'");
            } else {
                _log("Error: rm: unable to delete '$file'.");
                $success = false;
            }
        }
    }

    return $success;
}

// Function to delete a directory and its contents recursively
function delete_directory_recursive($dir) {
    // Ensure the directory exists
    if (!is_dir($dir)) {
        return false;
    }

    // Get all files and subdirectories
    $files = array_diff(scandir($dir), array('.', '..'));

    // Recursively delete each item inside the directory
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        // If it's a directory, recursively delete it
        if (is_dir($path)) {
            delete_directory_recursive($path);
        } else {
            unlink($path); // Delete file
        }
    }

    // After deleting all contents, remove the directory itself
    return rmdir($dir);
}

/******************************************************************************
 * CP                                                                         *
 ******************************************************************************/
function cp($args){
    $recursive = false;
    $explicit_destination = null;
    
    foreach ($args as $i => $arg) {
        if ($arg === '-r') {
            $recursive = true;
            unset($args[$i]); // Remove '-r'
        } elseif ($arg === '-t') {
            if (isset($args[$i + 1])) {
                $explicit_destination = $args[$i + 1];
                unset($args[$i + 1]); // Remove the explicitly passed target
                unset($args[$i]); // Remove '-t'
            } else {
                _log("cp: option requires an argument -- 't'");
                return false;
            }
        }
    }
    
    $args = array_values($args); // Reindex the array after deletions

    $args_count = count($args);
    
    if ($args_count <= 0) {
        _log("cp: missing file operand");
        return false;
    } elseif ($args_count == 1 && !isset($explicit_destination)) {
        _log("cp: missing destination file operand after '$args[0]'");
        return false;
    }
    
    if (!$explicit_destination) {
        $destination_abs = array_pop($args);
        $args_count = count($args);
    } else {
        $destination_abs = $explicit_destination;
    }
    unset($explicit_destination);

    // Absolute and relative destination
    $destination_abs = truepath($destination_abs);
    $destination_rel = preg_replace(ROOT_PATTERN, '', $destination_abs);
    
    if ($args_count == 1 && is_file(truepath($args[0]))) {
        if (!is_writable($destination_abs)) {
            _log("cp: '$destination_rel' does not have write permissions.");
            return false;
        }
    
        if (!is_dir($destination_abs)) {
            $dest_dir = dirname($destination_abs);
            $dest_dir_rel = dirname($destination_rel);
            if (!is_dir($dest_dir)) {
                _log("Error: cp: cannot stat '$dest_dir_rel': No such directory.");
                return false;
            }
    
            if (!is_writable($dest_dir)) {
                _log("cp: '$dest_dir_rel' does not have write permissions.");
                return false;
            }
        }
    } else { // $args_count = any value && is_dir(truepath($args[0]))
        if (!is_dir($destination_abs)) {
            _log("cp: cannot stat '$destination_rel': No such directory.");
            return false;
        } elseif (is_dir($destination_abs) && !is_writable($destination_abs)) {
            _log("cp: '$destination_rel' does not have write permissions.");
            return false;
        }
    }
    
    foreach ($args as $source) {
        $source_abs = truepath($source);
        $source_rel = preg_replace(ROOT_PATTERN, '', $source_abs);
        
        if (!is_readable($source_abs)) {
            _log("cp: '$source_rel' does not have read permissions.");
            continue;
        }

        if (!is_file($source_abs) && !is_dir($source_abs)) {
            _log("cp: cannot stat '$source_rel': No such file or directory.");
            continue;
        }

        if ($args_count == 1 && is_file($source_abs)) {
            // If only one source and it is a file
            if (is_dir($destination_abs)) {
                // If destination is a directory, copy with same filename
                if (!copy($source_abs, $destination_abs . DIRECTORY_SEPARATOR . basename($source_abs))) {
                    _log("cp: failed to copy '$source_rel' to '$destination_rel'");
                    return false;
                }
            } else {
                // If destination is an existing file to be overwritten, 
                // or is the new file name of $source_abs
                if (!copy($source_abs, $destination_abs)) {
                    _log("cp: failed to copy '$source_rel' to '$destination_rel'");
                    return false;
                }
            }
        } else {
            // If one or more sources, and they are files and/or folders
            if (is_file($source_abs)) {
                // If source is a file, copy to destination directory
                if (!copy($source_abs, $destination_abs . DIRECTORY_SEPARATOR . basename($source_abs))) {
                    _log("cp: failed to copy '$source_rel' to '$destination_rel'");
                    return false;
                }
            } elseif (is_dir($source_abs)) {
                // If source is a directory, copy recursively
                if ($recursive) {
                    $source_dir_name = basename($source_abs);
                    $ending = DIRECTORY_SEPARATOR . $source_dir_name;
                    $destination_dir = $destination_abs . $ending;
                    $destination_dir_rel = $destination_rel . $ending;
                    
                    // Ensure destination directory exists
                    if (!is_dir($destination_dir) && !mkdir($destination_dir, 0755, true)) {
                        _log("cp: failed to create directory '$destination_dir_rel'");
                        return false;
                    }

                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source_abs, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($files as $fileinfo) {
                        $ending = DIRECTORY_SEPARATOR . $files->getSubPathName();
                        $target = $destination_dir . $ending;
                        $target_rel = $destination_dir_rel . $ending;
                        if ($fileinfo->isDir()) {
                            if (!is_dir($target) && !mkdir($target, 0755, true)) {
                                _log("cp: failed to create directory '$target_rel'");
                                return false;
                            }
                        } else {
                            if (!copy($fileinfo->getRealPath(), $target)) {
                                _log("cp: failed to copy file '$source_rel' to '$target_rel'");
                                return false;
                            }
                        }
                    }
                } else {
                    _log("cp: -r not specified; omitting '$source_rel' directory");
                    return false;
                }
            }
        }
    }

    return $args; // return $args just in case MV is calling CP
}

/******************************************************************************
 * MV                                                                         *
 ******************************************************************************/
function mv($args){
    $explicit_destination = null;
    
    foreach ($args as $i => $arg) {
        if ($arg === '-t') {
            if (isset($args[$i + 1])) {
                $explicit_destination = $args[$i + 1];
                unset($args[$i + 1]); // Remove the explicitly passed target
                unset($args[$i]); // Remove '-t'
            } else {
                _log("mv: option requires an argument -- 't'");
                return false;
            }
        }
    }

    $args = array_values($args); // Reindex the array after deletions
    $args_count = count($args);

    if ($args_count <= 0){
        _log("mv: missing file operand");
        return false;
    } elseif ($args_count == 1 && !isset($explicit_destination)){
        _log("mv: missing destination file operand after $args[0]");
        return false;
    } elseif ( $args_count == 2 || ($args_count == 1 && isset($explicit_destination)) ){
        $source = truepath($args[0]);
        $destination = truepath($explicit_destination ?? $args[1]);

        if (!file_exists($source)){
            _log("mv: cannot stat '$args[0]': No such file or directory");
            return false;
        }
        
        if (!file_exists(dirname($destination))){
            _log("mv: cannot stat '".dirname($args[1])."/': No such file or directory");
            return false;
        }
        
        if ( is_dir($destination) && file_exists($source) ){
            $destination = $destination . DIRECTORY_SEPARATOR . basename($source);
        }

        return rename($source, $destination); // returns true or false
    } else { // $args_count >= 3
        if (isset($explicit_destination)){
            $destination_rel = $explicit_destination;
        } else {
            $destination_rel = $args[count($args) - 1];
            unset($args[count($args) - 1]);
            $args = array_values($args); // Reindex the array after deletion
        }
        
        $destination_abs = truepath($destination_rel);
        
        if (!is_dir($destination_abs)){
            _log("mv: target '$destination_rel' is not a directory");
            return false;
        }
        
        if (is_dir($destination_abs) && !is_writable($destination_abs)){
            _log("mv: '$destination_rel' does not have write permissions.");
            return false;
        }
        
        foreach ($args as $source) {
            $source_abs = truepath($source);
            $destination_final = $destination_abs;
            
            if (!file_exists($source_abs)){
                _log("mv: cannot stat '$source': No such file or directory");
                continue;
            } else { // file_exists($source_abs)
                $destination_final .= DIRECTORY_SEPARATOR . basename($source);
            }
            
            rename($source_abs, $destination_final);
        }
        
        return true;
    }
}

/******************************************************************************
 * HELP / ?                                                                   *
 ******************************************************************************/
function help($args) {
    if ($args) {
        _log("help: too many arguments");
        return;
    }

    // HEREDOC
    // https://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.heredoc
    $help = <<<"EOD"
<pre>
<b>AVAILABLE COMMANDS</b>

<b>about</b>
    usage: about
    Copyright information.

<b>cd</b>
    usage: cd &lt;path&gt;
    Change directory to &lt;path&gt;.

<b>clear</b>
    usage: clear
    Clears the screen.

<b>cp</b>
    usage: cp &lt;SOURCE(S)&gt; &lt;DESTINATION&gt;
    Copy SOURCE(S) to DESTINATION. Source(s) and destination must exist.

    -r     copy directories recursively.
    -t     explicit destination. If omitted, the last argument will be
           considered as the destination.

    When copying a single file (but not a directory), it is possible to copy it
    to the destination with a new name. If the destination file name is omitted
    (i.e., only the destination directory is given), the file will be copied to
    the destination with the same name as the source.
    
    If the destination file or folder exists, it will be overwritten.

<b>help</b>
    usage: help
    Show this help, listing all available commands.

<b>ls</b>
    usage: ls &lt;folder(s) and/or file(s) name(s)&gt;
    List contents of folder(s) or attributes of file(s). Equivalent to GNU/Linux
    'ls -laAgG --group-directories-first --color=auto'. It is possible to list
    multiple directories or even a single file.

<b>mkdir</b>
    usage: mkdir &lt;directory(ies)&gt;
    Creates the &lt;directory(ies)&gt; if they do not already exist. Creation is
    recursive.

<b>mv</b>
    usage: mv &lt;SOURCE(S)&gt; &lt;DESTINATION&gt;
    Move SOURCE(S) to DESTINATION.

    -t     explicit destination. If omitted, the last argument will be
           considered as the destination.

    When only 2 arguments are given, the command will rename the SOURCE file or
    folder to the name DESTINATION. Both SOURCE and DESTINATION can be absolute
    or relative paths, and can be in different folders, in which case SOURCE
    will be moved to the DESTINATION folder.

    To keep the name:
        'mv /source/file.txt /destination/file.txt'
        'mv /source/file.txt /destination'
    
    To rename:
        'mv /source/file.txt /destination/newfile.txt'
        'mv file.txt newfile.txt'
    
    When more than 2 arguments are given, all SOURCES will be moved to
    DESTINATION keeping their names.

    In either case, SOURCE will be deleted.
    
<b>pwd</b>
    usage: pwd
    Print name of current working directory.

<b>rm</b>
    usage: rm &lt;file(s) and/or folder(s)&gt;
    Delete one or more files and folders.

    -r     deletes non-empty directories recursively.

<b>rmdir</b>
    usage: rmdir &lt;directory(ies)&gt;
    Delete empty directories.

<b>unzap</b>
    usage: unzap &lt;file name&gt; &lt;extract to path&gt;
    Unarchiver for a variety of files. Supported extensions: gz, bz2, zip, tar,
    tar.gz, tgz, tar.bz2, tbz, tbz2, rar.

    Default extraction path is the current directory, not the directory where
    the file is.

<b>CONVENTIONS</b>

    .      current folder
    ..     upper folder
    /      root folder
    '\ '   escape sequence for space, as in: file\ name
</pre>
EOD;

    _log($help);
    
    return true;
}

/******************************************************************************
 * ABOUT                                                                      *
 ******************************************************************************/
function about($args){
    if ($args){
        $args = implode(' ', $args);
        
        switch ($args) {
            case 'myself':
                _log('about: I have nothing to say about you...');
                break;
            case 'yourself':
                _log('about: Come on, dude. Are you an idiot? Just write "about", NO arguments.');
                break;
            default:
                _log("about: I don't know who \"$args\" is, I can only talk about myself.");
                break;
        }
        
        return false;
    } else {
        $contents = file_exists('LICENSE.md') ? file_get_contents('LICENSE.md') :
            file_get_contents('https://raw.githubusercontent.com/nerun/terminix/refs/heads/main/LICENSE.md');
        
        _log('<pre>' . $contents . '</pre>');
    }
    
    return true;
}
?>
