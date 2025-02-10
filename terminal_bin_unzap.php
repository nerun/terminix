<?php
/* terminal_bin_unzap.php - version 1 - 2025-02-10
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

/* The UNZAP script uses functions from the "terminal_bin.php" script. Since
 * both scripts are included in the main script ("terminal.php"), this script
 * can access them. Otherise:
 * include('./terminal_bin.php');
 */

// Avoid server timeout
set_time_limit(0);
ini_set('max_execution_time', 0);

function bunzip2($data, $ext, $expath) {
    if (!extension_loaded('bz2')) {
        _log("Error: unzap: Bzip2 library is not available.");
        return false;
    }
    
    $extensions = [
        'tbz2' => '.tar',
        'tbz'  => '.tar',
        'bz2'  => ''
    ];

    if (!isset($extensions[$ext])) {
        _log("Error: $data is not a bzip2 file.");
        return false;
    }

    $ext_data = str_replace(".$ext", $extensions[$ext], $data);
    $filename = substr($ext_data, strrpos($ext_data, '/') + 1);
    $ext_data = $expath . DIRECTORY_SEPARATOR . $filename;

    $bz = bzopen($data, 'r') or die("Couldn't open $data.");
    $uncompressed = '';

    while (!feof($bz)) {
        $uncompressed .= bzread($bz, 8192);
    }

    bzclose($bz);
    file_put_contents($ext_data, $uncompressed);

    // If the extracted file is a tarball, extract it as well.
    is_tarball($ext_data);

    return true;
}

function gunzip($data, $ext, $expath) {
    if (!extension_loaded('zlib')) {
        _log("Error: unzap: Gzip (zlib) library is not available.");
        return false;
    }
    
    $extensions = [
        'tgz' => '.tar',
        'gz'  => ''
    ];

    if (!isset($extensions[$ext])) {
        _log("Error: $data is not a gzip file.");
        return false;
    }

    $ext_data = str_replace(".$ext", $extensions[$ext], $data);
    $filename = substr($ext_data, strrpos($ext_data, '/') + 1);
    $ext_data = $expath . DIRECTORY_SEPARATOR . $filename;

    if (filesize($data) <= (1024 * 1024 * 70)) {
        // For small files (<= 70 MB), read and decode in one go.
        file_put_contents($ext_data, gzdecode(file_get_contents($data)));
    } else {
        // For larger files, use streaming to avoid memory issues.
        $gz = gzopen($data, 'rb') or die("Couldn't open $data.");
        $ungz = fopen($ext_data, 'wb');

        while (!gzeof($gz)) {
            fwrite($ungz, gzread($gz, 8192));
        }

        fclose($ungz);
        gzclose($gz);
    }

    // If the extracted file is a tarball, extract it as well.
    is_tarball($ext_data);

    return true;
}

function unrar($data, $ext, $expath) {
    if (!class_exists('RarArchive')) {
        _log("Error: unzap: RAR library is not available.");
        return false;
    }

    // Open the RAR file
    $rar = RarArchive::open($data);
    if ($rar === false) {
        _log("Error: unrar: failed to open the RAR file: $data");
        return false;
    }

    // Get the entries of the RAR file
    $entries = $rar->getEntries();
    if ($entries === false) {
        $rar->close();
        _log("unrar: error retrieving items from the RAR file: $data");
        return false;
    }

    // Extract the files
    foreach ($entries as $entry) {
        if (!$entry->extract($expath)) {
            $rar->close();
            _log("Error: unrar: failed to extract file: " . $entry->getName());
            return false;
        }
    }

    $rar->close();
    _log("extracted successfully!");
    return true;
}

function untar($data, $ext = null, $expath = null) {
    if (!extension_loaded('phar')) {
        _log("Error: unzap: Tarball (Phar) library is not available.");
        return false;
    }
    
    $expath = $expath ?? dirname($data);

    $phar = new PharData($data);
    $success = $phar->extractTo($expath, null, true);

    if ($success) {
        _log("unarchived successfully!");
    } else {
        _log("Error: failed to be unarchived!");
        return false;
    }

    return true;
}

function unzip($data, $ext, $expath) {
    if (!extension_loaded('zip')) {
        _log("Error: unzap: Zip library is not available.");
        return false;
    }

    $zip = new ZipArchive;
    $success = true;

    if ($zip->open($data)) {
        if ($zip->extractTo($expath)) {
            _log("extracted successfully!");
        } else {
            _log("Error: failed to be extracted!");
            $success = false;
        }
        $zip->close();
    } else {
        _log("Error: couldn't open $data.");
        $success = false;
    }
    
    return $success;
}

// If file is a tar.gz or tar.bz2
function is_tarball($filename) {
    if (@file_exists($filename)) {
        _log("extracted successfully!");
        
        // Now check if the extracted file is a tarball.
        $filename_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if ( $filename_ext == 'tar' ) {
            $filename_short = preg_replace(ROOT_PATTERN, '', $filename);
            _log("unarchiving {$filename_short}...");
            untar($filename);
            unlink($filename); // delete file.tar
        }
    } else {
        _log("Error: failed to be extracted!");
        return false;
    }
    
    return true;
}

function unzap($args) {
    $count_args = count($args);

    if ($count_args > 2) {
        _log("unzap: too many arguments");
        return false;
    } elseif ($count_args <= 0) {
        _log("Error: missing argument!");
        _log("unzap: compressed data cannot be read from a terminal.");
        return false;
    }

    $zipfile = truepath($args[0]);
    $exdir = ($count_args == 2) ? rtrim(truepath($args[1]), DIRECTORY_SEPARATOR) : CWD;

    if (!is_dir($exdir)) {
        $error = is_file($exdir) ? "is a file, not a path." : "does not exist.";
        _log("Error: unzap: \"$args[1]\" $error");
        return false;
    } elseif (!is_writable($exdir)) {
        _log("Error: unzap: \"$args[1]\" does not have write permissions.");
        return false;
    }

    if (!@file_exists($zipfile)) {
        _log("Error: $args[0]: No such file or directory.");
        return false;
    } elseif (!is_readable($zipfile)) {
        _log("Error: unzap: \"$args[0]\" does not have read permissions.");
        return false;
    }

    $ext = strtolower(pathinfo($zipfile, PATHINFO_EXTENSION));
    $decomp = $exdir != CWD ? "extracting $args[0] to $args[1]..." : "extracting $args[0] to current directory...";

    $supportedFormats = [
        'bz2' => 'bunzip2',
        'tbz' => 'bunzip2',
        'tbz2' => 'bunzip2',
        'gz' => 'gunzip',
        'tgz' => 'gunzip',
        'rar' => 'unrar',
        'tar' => 'untar',
        'zip' => 'unzip'
    ];

    if (!isset($supportedFormats[$ext])) {
        _log("unzap: unsupported file format.");
        return false;
    }

    _log($decomp);
    $supportedFormats[$ext]($zipfile, $ext, $exdir);

    return true;
}

?>
