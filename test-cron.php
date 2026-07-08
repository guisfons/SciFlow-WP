<?php
require_once('../../../wp-load.php');
$sm = new SciFlow_Status_Manager();
echo "Checking deadlines...\n";
$sm->check_corrections_deadlines();
echo "Done.\n";
