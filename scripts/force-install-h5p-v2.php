<?php
$plugin = H5P_Plugin::get_instance();
$core = $plugin->get_h5p_instance('core');
$interface = $plugin->get_h5p_instance('interface');
$file = '/tmp/arithmetic.h5p';

$temp_dir = $interface->getUploadedH5pFolderPath();
if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    if (!$zip->extractTo($temp_dir)) {
        die("FAILURE: Could not extract zip to $temp_dir\n");
    }
    $zip->close();
    $core->saveLibraries($temp_dir, true);
    echo "SUCCESS: Libraries saved.\n";
} else {
    echo "FAILURE: Could not open zip $file\n";
}
