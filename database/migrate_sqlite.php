<?php
$root = dirname(__DIR__);
$sourceSql = $root . '/db_partner.sql';
$targetDb = $root . '/database/database.db';

if (!file_exists($sourceSql)) {
    fwrite(STDERR, "Error: No se encontró el archivo de dump MySQL: {$sourceSql}\n");
    exit(1);
}

if (!is_dir(dirname($targetDb))) {
    mkdir(dirname($targetDb), 0755, true);
}

if (file_exists($targetDb)) {
    unlink($targetDb);
}

$pdo = new PDO('sqlite:' . $targetDb);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec("PRAGMA encoding = 'UTF-8'");

$lines = file($sourceSql, FILE_IGNORE_NEW_LINES);
$statement = '';
$createStatements = [];
$inserts = [];
$primaryKeys = [];
$foreignKeys = [];
$lineNumber = 0;

foreach ($lines as $line) {
    $lineNumber++;
    $trimmed = trim($line);
    if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || preg_match('/^\/\*!/', $trimmed)) {
        continue;
    }

    $statement .= $line . "\n";
    if (preg_match('/;\s*$/', $line) === 0) {
        continue;
    }

    $stmt = trim($statement);
    $statement = '';

    if (preg_match('/^CREATE TABLE `?([^` ]+)`?/i', $stmt, $matches)) {
        $table = $matches[1];
        $createStatements[$table] = $stmt;
        continue;
    }

    if (preg_match('/^ALTER TABLE `?([^` ]+)`?\s+ADD PRIMARY KEY \(`?([^`]+)`?\)/i', $stmt, $matches)) {
        $table = $matches[1];
        $columns = array_map('trim', explode(',', str_replace('`', '', $matches[2])));
        $primaryKeys[$table] = $columns;
        continue;
    }

    if (preg_match('/^ALTER TABLE `?([^` ]+)`?\s+MODIFY `?([^`]+)`?\s+[^;]+AUTO_INCREMENT/i', $stmt, $matches)) {
        $table = $matches[1];
        $column = $matches[2];
        if (!isset($primaryKeys[$table])) {
            $primaryKeys[$table] = [$column];
        }
        continue;
    }

    if (preg_match('/^ALTER TABLE `?([^` ]+)`?\s+ADD CONSTRAINT `?[^` ]+`?\s+FOREIGN KEY \(`?([^`]+)`?\)\s+REFERENCES `?([^` ]+)`? \(`?([^`]+)`?\)(.*)/i', $stmt, $matches)) {
        $table = $matches[1];
        $column = $matches[2];
        $refTable = $matches[3];
        $refColumn = $matches[4];
        $rest = trim($matches[5]);
        $rest = preg_replace('/,$/', '', $rest);
        $rest = preg_replace('/\s*ON UPDATE NO ACTION/i', '', $rest);
        $rest = preg_replace('/\s*ON DELETE NO ACTION/i', '', $rest);
        $rest = preg_replace('/\s*;$/', '', $rest);
        $rest = trim($rest);
        $foreignKeys[$table][] = sprintf('FOREIGN KEY (%s) REFERENCES %s(%s)%s', $column, $refTable, $refColumn, $rest !== '' ? ' ' . $rest : '');
        continue;
    }

    if (preg_match('/^INSERT INTO /i', $stmt)) {
        $sql = preg_replace('/`([^`]+)`/', '$1', $stmt);
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $inserts[] = $sql;
        continue;
    }
}

function normalizeCreateStatement(string $createSql, array $primaryKeys, array $foreignKeys): string
{
    $createSql = preg_replace('/`([^`]+)`/', '$1', $createSql);
    $createSql = preg_replace('/\s+COLLATE\s+[^\s,]+/i', '', $createSql);
    $createSql = preg_replace('/\s+CHARACTER SET\s+[^\s,]+/i', '', $createSql);
    $createSql = preg_replace('/\bENGINE\s*=\s*[^\s;]+/i', '', $createSql);
    $createSql = preg_replace('/\)\s*DEFAULT\s+CHARSET=[^;]+;?/i', ')', $createSql);
    $createSql = preg_replace('/\)\s*COLLATE=[^;]+;?/i', ')', $createSql);
    $createSql = preg_replace('/ON UPDATE\s+CURRENT_TIMESTAMP/i', '', $createSql);
    $createSql = preg_replace('/DEFAULT\s+NULL\s*\(\s*\)/i', 'DEFAULT NULL', $createSql);
    $createSql = preg_replace('/DEFAULT\s+CURRENT_TIMESTAMP\s*\(\s*\)/i', 'DEFAULT CURRENT_TIMESTAMP', $createSql);
    $createSql = preg_replace('/DEFAULT\s+current_timestamp\s*\(\s*\)/i', 'DEFAULT CURRENT_TIMESTAMP', $createSql);
    $createSql = preg_replace('/CURRENT_TIMESTAMP\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $createSql);
    $createSql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $createSql);
    $createSql = preg_replace('/\bUNSIGNED\b/i', '', $createSql);
    $createSql = preg_replace('/\bINT\(\d+\)/i', 'INTEGER', $createSql);
    $createSql = preg_replace('/\bBIGINT\(\d+\)/i', 'INTEGER', $createSql);
    $createSql = preg_replace('/\bTINYINT\(\d+\)/i', 'INTEGER', $createSql);
    $createSql = preg_replace('/\bMEDIUMINT\(\d+\)/i', 'INTEGER', $createSql);
    $createSql = preg_replace('/\bSMALLINT\(\d+\)/i', 'INTEGER', $createSql);
    $createSql = preg_replace('/\bVARCHAR\(\d+\)/i', 'TEXT', $createSql);
    $createSql = preg_replace('/\bTEXT\b/i', 'TEXT', $createSql);
    $createSql = preg_replace('/\bDECIMAL\(\d+,\d+\)/i', 'NUMERIC', $createSql);
    $createSql = preg_replace('/\bFLOAT\(\d+\)/i', 'REAL', $createSql);
    $createSql = preg_replace('/\bDOUBLE\(\d+\)/i', 'REAL', $createSql);
    $createSql = preg_replace_callback('/\b(\w+)\s+enum\(([^)]+)\)/i', function ($match) {
        return sprintf('%s TEXT CHECK(%s IN (%s))', $match[1], $match[1], $match[2]);
    }, $createSql);
    $createSql = preg_replace('/\s+COMMENT\s+\'[^\']*\'/i', '', $createSql);
    $createSql = preg_replace('/\s+COMMENT\s+"[^"]*"/i', '', $createSql);
    $createSql = preg_replace('/,\s*\)/', ')', $createSql);

    if (preg_match('/^CREATE TABLE\s+(\w+)\s*\((.*)\)\s*;?$/is', $createSql, $matches)) {
        $tableName = $matches[1];
        $columnsPart = trim($matches[2]);
        $lines = preg_split('/,\s*(?![^\(]*\))/m', $columnsPart);
        $cleanLines = [];
        $hasPrimaryKey = false;
        $autoIncrementIdApplied = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (stripos($trimmed, 'PRIMARY KEY') !== false) {
                $hasPrimaryKey = true;
            }
            if (preg_match('/^(KEY|UNIQUE KEY|INDEX)\s+/i', $trimmed)) {
                continue;
            }
            if (preg_match('/^CONSTRAINT\s+/i', $trimmed)) {
                continue;
            }
            if (!$autoIncrementIdApplied && preg_match('/^id\s+/i', $trimmed)) {
                $trimmed = preg_replace('/^id\s+.+$/i', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $trimmed);
                $hasPrimaryKey = true;
                $autoIncrementIdApplied = true;
            }
            $cleanLines[] = $trimmed;
        }

        if (!$hasPrimaryKey && isset($primaryKeys[$tableName])) {
            $pkColumns = $primaryKeys[$tableName];
            if (count($pkColumns) === 1 && strtolower($pkColumns[0]) === 'id') {
                foreach ($cleanLines as $index => $line) {
                    $trimmedLine = trim($line);
                    if (preg_match('/^id\s+/i', $trimmedLine)) {
                        $cleanLines[$index] = preg_replace('/^id\s+.+$/i', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $trimmedLine);
                        $hasPrimaryKey = true;
                        break;
                    }
                }
            }
            if (!$hasPrimaryKey) {
                $cleanLines[] = 'PRIMARY KEY (' . implode(', ', $pkColumns) . ')';
                $hasPrimaryKey = true;
            }
        }

        if (!$hasPrimaryKey) {
            foreach ($cleanLines as $index => $line) {
                $trimmedLine = trim($line);
                if (preg_match('/^id\s+/i', $trimmedLine)) {
                    $cleanLines[$index] = preg_replace('/^id\s+.+$/i', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $trimmedLine);
                    $hasPrimaryKey = true;
                    break;
                }
            }
        }

        if (isset($foreignKeys[$tableName]) && count($foreignKeys[$tableName]) > 0) {
            foreach ($foreignKeys[$tableName] as $fk) {
                $cleanLines[] = $fk;
            }
        }

        $createSql = "CREATE TABLE {$tableName} (" . PHP_EOL . implode(',' . PHP_EOL, $cleanLines) . PHP_EOL . ');';
    }

    return $createSql;
}

foreach ($createStatements as $table => $createSql) {
    $normalized = normalizeCreateStatement($createSql, $primaryKeys, $foreignKeys);
    try {
        $pdo->exec($normalized);
        fwrite(STDOUT, "Created table: {$table}\n");
    } catch (PDOException $e) {
        fwrite(STDERR, "Error creating table {$table}: " . $e->getMessage() . "\n");
        fwrite(STDERR, "SQL:\n{$normalized}\n\n");
        exit(1);
    }
}

$pdo->beginTransaction();
foreach ($inserts as $insertSql) {
    try {
        $pdo->exec($insertSql);
    } catch (PDOException $e) {
        fwrite(STDERR, "Error inserting data: " . $e->getMessage() . "\n");
        fwrite(STDERR, "SQL:\n{$insertSql}\n\n");
        exit(1);
    }
}
$pdo->commit();
$pdo->exec('PRAGMA foreign_keys = ON');

fwrite(STDOUT, "SQLite database created at {$targetDb}\n");
