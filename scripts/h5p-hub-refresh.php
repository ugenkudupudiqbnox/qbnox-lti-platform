<?php
$plugin = H5P_Plugin::get_instance();
$core = $plugin->get_h5p_instance('core');
if ($core->updateContentTypeCache()) {
    echo "✅ H5P Hub Content Type Cache successfully updated.\n";
} else {
    echo "❌ H5P Hub Content Type Cache update FAILED.\n";
}
