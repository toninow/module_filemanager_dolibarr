<?php
require_once "../../main.inc.php";
echo "DOL_MAIN_URL_ROOT: " . $dolibarr_main_url_root . "\n";
echo "dol_buildpath result: " . dol_buildpath("/custom/filemanager/scripts/test_simple.php", 1) . "\n";
