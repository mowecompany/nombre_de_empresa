<?php
require_once __DIR__ . '/Config/database.php';
$db = Database::connect();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Vista de base de datos</title>
  <style>
    body{font-family:Arial,sans-serif;margin:20px;}
    table{border-collapse:collapse;width:100%;margin-bottom:24px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;}
    th{background:#f2f2f2;}
    pre{background:#111;color:#eee;padding:12px;overflow:auto;}
    .wrap{max-width:1200px;margin:auto;}
  </style>
</head>
<body>
<div class="wrap">
  <h1>Base de datos</h1>
  <p>Abre esta página en el navegador para inspeccionar tablas y datos.</p>
  <?php
  $tables = [];
  if (strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
      $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
  }
  foreach ($tables as $table) {
      echo '<h2>' . htmlspecialchars($table) . '</h2>';
      try {
          $stmt = $db->query('SELECT * FROM ' . $table . ' LIMIT 20');
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          if (!$rows) {
              echo '<p>Sin filas.</p>';
              continue;
          }
          echo '<table>';
          echo '<tr>';
          foreach (array_keys($rows[0]) as $col) {
              echo '<th>' . htmlspecialchars($col) . '</th>';
          }
          echo '</tr>';
          foreach ($rows as $row) {
              echo '<tr>';
              foreach ($row as $value) {
                  $text = is_scalar($value) ? (string)$value : json_encode($value);
                  echo '<td>' . htmlspecialchars(substr($text, 0, 180)) . '</td>';
              }
              echo '</tr>';
          }
          echo '</table>';
      } catch (Throwable $e) {
          echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
      }
  }
  ?>
</div>
</body>
</html>
