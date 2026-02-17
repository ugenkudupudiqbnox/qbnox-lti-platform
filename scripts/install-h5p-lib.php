<?php
/**
 * Manually install H5P library from a local .h5p file
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = H5P_Plugin::get_instance();
$interface = $plugin->get_h5p_instance('interface');
$core = $plugin->get_h5p_instance('core');
$validator = $plugin->get_h5p_instance('validator');
$storage = $plugin->get_h5p_instance('storage');

$path = '/tmp/H5P.ArithmeticQuiz.h5p';

if (!file_exists($path)) {
    echo "Error: File not found at $path\n";
    exit(1);
}

echo "Processing $path...\n";

// Get the temp path H5P expects
$temp_path = $core->fs->getTmpPath();
$path_h5p = $temp_path . '.h5p';

echo "Temp path: $temp_path\n";

if (!is_dir($temp_path)) {
    if (!mkdir($temp_path, 0777, true)) {
        echo "Error: Failed to create temp path $temp_path\n";
        exit(1);
    }
}

// Copy the H5P file to the expected location
if (!copy($path, $path_h5p)) {
    echo "Error: Failed to copy $path to $path_h5p\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($path_h5p) === TRUE) {
    $zip->extractTo($temp_path);
    $zip->close();
    echo "Extracted to $temp_path\n";
} else {
    echo "Error: Failed to open zip file $path_h5p\n";
    exit(1);
}

// This is the sequence for installing libraries from a folder
echo "Validating package...\n";
if ($validator->isValidPackage(true, false)) {
    echo "Validation successful. Saving libraries...\n";
    // savePackage(null, null, true) saves libraries from the validated temp folder
    $storage->savePackage(null, null, true);
    
    // Check for errors
    $errors = $core->getMessages('error');
    if (!empty($errors)) {
        echo "Errors during save:\n";
        foreach ($errors as $err) echo " - $err\n";
    } else {
        echo "Successfully installed libraries from $path\n";
    }
} else {
    echo "Validation failed:\n";
    foreach ($core->getMessages('error') as $err) echo " - $err\n";
}

// Cleanup handled by H5P usually, but let's be safe
// (Actually H5P will clean up the temp folder on the next run or session end)
