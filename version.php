<?php
// PHPバージョンのみ表示
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Required: 8.1.0 or higher\n";
echo "\n";
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    echo "Status: OK ✓\n";
} else {
    echo "Status: NG ✗\n";
    echo "Please upgrade PHP to version 8.1 or higher\n";
}
