<?php
$plugin = H5P_Plugin::get_instance();
$interface = $plugin->get_h5p_instance('interface');
$validator = $plugin->get_h5p_instance('validator');
$storage = $plugin->get_h5p_instance('storage');

$file = '/tmp/arithmetic.h5p';

$temp_dir = $interface->getUploadedH5pFolderPath();
if (!is_dir($temp_dir)) { 
    mkdir($temp_dir, 0777, true); 
}
$temp_file = $interface->getUploadedH5pPath();
if (!copy($file, $temp_file)) {
    die("Error: Could not copy $file to $temp_file\n");
}

if ($validator->isValidPackage(true, false)) {
    $storage->savePackage(null, null, true);
    echo "SUCCESS: Library installed.\n";
} else {
    echo "FAILURE: Validation failed.\n";
    echo "Core Errors: ";
    print_r($interface->getMessages('error'));
    echo "Plugin instance messages: ";
    print_r(H5P_Plugin_Admin::get_messages('error'));
}
