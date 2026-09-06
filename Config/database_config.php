<?php
// Configuración explícita para forzar SQLite
define('DB_CONNECTION', 'sqlite');
// Usar siempre ruta relativa para que funcione tanto en desarrollo como en producción
define('SQLITE_PATH', 'database/database.db');
?>