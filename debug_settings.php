<?php
require_once '/home/guisfons/doppiod/inscricao-enfrute/wp-load.php';
$settings = get_option('sciflow_settings');
file_put_contents('/home/guisfons/doppiod/inscricao-enfrute/wp-content/plugins/SciFlow-WP/debug_settings.json', json_encode($settings, JSON_PRETTY_PRINT));
print "Settings exported.\n";
