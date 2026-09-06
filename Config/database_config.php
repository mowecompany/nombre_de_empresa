<?php
// Configuración explícita para forzar SQLite
define('DB_CONNECTION', 'sqlite');
define('SQLITE_PATH', dirname(__DIR__) . '/database/database.db');
?>