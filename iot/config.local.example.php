<?php
// Copy this file to config.local.php ON THE SERVER (cPanel File Manager) and fill in
// the values from cPanel > MySQL Databases. cPanel adds your account prefix to both
// the database name and the user, e.g. "cpuser_iotdb" / "cpuser_iotuser".
// config.local.php is excluded from git and from the auto-deploy.
return [
    'host'     => 'localhost',
    'database' => 'cpuser_iotdb',
    'username' => 'cpuser_iotuser',
    'password' => 'CHANGE_ME',
];
