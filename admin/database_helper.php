<?php
declare(strict_types=1);

/**
 * admin/database_helper.php
 *
 * Backend AJAX endpoint for the Jotify Database Explorer.
 * Accessible to Superadmin (privilege 3) only.
 */

require_once(__DIR__ . '/../includes/auth.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

if ($privilege < 3) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang (Superadmin vereist)']);
    exit();
}

$action = trim((string)($_POST['action'] ?? ''));

/**
 * Return all base tables in current database.
 *
 * @param mysqli $conn
 * @return array<string>
 */
function getWhitelistedTables(mysqli $conn): array {
    $tables = [];
    $res = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME ASC");
    if ($res) {
        while ($row = $res->fetch_row()) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

/**
 * Return detailed column and primary key schema for a table.
 *
 * @param mysqli $conn
 * @param string $table
 * @return array<string, mixed>|null
 */
function getTableSchema(mysqli $conn, string $table): ?array {
    $tables = getWhitelistedTables($conn);
    if (!in_array($table, $tables, true)) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION ASC
    ");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();

    $columns = [];
    while ($col = $res->fetch_assoc()) {
        $enumValues = [];
        if (strtolower($col['DATA_TYPE']) === 'enum') {
            if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $col['COLUMN_TYPE'], $matches)) {
                $enumValues = $matches[1];
            }
        }

        $columns[$col['COLUMN_NAME']] = [
            'name' => $col['COLUMN_NAME'],
            'data_type' => strtolower($col['DATA_TYPE']),
            'column_type' => $col['COLUMN_TYPE'],
            'is_nullable' => ($col['IS_NULLABLE'] === 'YES'),
            'default' => $col['COLUMN_DEFAULT'],
            'extra' => $col['EXTRA'],
            'is_auto_increment' => (stripos($col['EXTRA'], 'auto_increment') !== false),
            'is_primary' => ($col['COLUMN_KEY'] === 'PRI'),
            'enum_values' => $enumValues
        ];
    }
    $stmt->close();

    $stmtPk = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION ASC
    ");
    $stmtPk->bind_param('s', $table);
    $stmtPk->execute();
    $resPk = $stmtPk->get_result();
    $primaryKey = [];
    while ($p = $resPk->fetch_row()) {
        $primaryKey[] = $p[0];
    }
    $stmtPk->close();

    return [
        'table' => $table,
        'columns' => $columns,
        'primary_key' => $primaryKey
    ];
}

/**
 * Check SQL for forbidden DDL, administrative commands, or multi-statement chains.
 *
 * @param string $sql
 * @return string|null Forbidden keyword or null if clean
 */
function checkForbiddenKeywords(string $sql): ?string {
    $clean = preg_replace('/(\/\*.*?\*\/|--[^\r\n]*|#[^\r\n]*)/s', ' ', $sql);
    $clean = trim((string)$clean);

    $blocked = [
        'ALTER', 'DROP', 'TRUNCATE', 'CREATE', 'RENAME',
        'GRANT', 'REVOKE', 'LOAD_FILE', 'INTO OUTFILE',
        'INTO DUMPFILE', 'LOCK TABLES', 'SET GLOBAL'
    ];

    foreach ($blocked as $b) {
        if (preg_match('/\b' . preg_quote($b, '/') . '\b/i', $clean)) {
            return $b;
        }
    }

    $withoutStrings = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`/', '', $clean);
    if (substr_count(rtrim((string)$withoutStrings, "; \t\n\r"), ';') > 0) {
        return 'MULTIPLE_STATEMENTS';
    }

    return null;
}

/**
 * Return initial keyword of a SQL query.
 *
 * @param string $sql
 * @return string
 */
function getFirstKeyword(string $sql): string {
    $clean = preg_replace('/(\/\*.*?\*\/|--[^\r\n]*|#[^\r\n]*)/s', ' ', $sql);
    $clean = trim((string)$clean);
    if (preg_match('/^([A-Za-z]+)/', $clean, $m)) {
        return strtoupper($m[1]);
    }
    return '';
}

/**
 * Detect simple single-table SELECT to enable inline edits in manual mode.
 *
 * @param string $sql
 * @param array<string> $whitelistedTables
 * @return string|null Table name if simple single-table query
 */
function detectSingleTableQuery(string $sql, array $whitelistedTables): ?string {
    $clean = preg_replace('/(\/\*.*?\*\/|--[^\r\n]*|#[^\r\n]*)/s', ' ', $sql);
    $clean = trim((string)$clean);
    if (preg_match('/\b(JOIN|UNION|GROUP\s+BY|DISTINCT)\b/i', $clean)) {
        return null;
    }
    if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+[`]?([a-zA-Z0-9_]+)[`]?(?:\s+WHERE\s+(.*?))?(?:\s+ORDER\s+BY\s+(.*?))?(?:\s+LIMIT\s+(\d+)(?:\s*,\s*(\d+))?)?\s*;?$/is', $clean, $m)) {
        $tbl = $m[2];
        if (in_array($tbl, $whitelistedTables, true)) {
            return $tbl;
        }
    }
    return null;
}

try {
    switch ($action) {
        case 'list_tables':
            $tables = getWhitelistedTables($conn);
            echo json_encode(['success' => true, 'tables' => $tables]);
            break;

        case 'table_schema':
            $table = trim((string)($_POST['table'] ?? ''));
            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }
            echo json_encode(['success' => true, 'schema' => $schema]);
            break;

        case 'build_query':
            $table = trim((string)($_POST['table'] ?? ''));
            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }

            $whereParts = [];
            $previewWhereParts = [];
            $params = [];

            $rawFilters = $_POST['filters'] ?? '[]';
            $filters = is_string($rawFilters) ? json_decode($rawFilters, true) : (array)$rawFilters;
            if (is_array($filters)) {
                foreach ($filters as $f) {
                    $col = trim((string)($f['column'] ?? ''));
                    $op = strtoupper(trim((string)($f['operator'] ?? '=')));
                    $val = (string)($f['value'] ?? '');

                    if (!isset($schema['columns'][$col])) {
                        continue;
                    }

                    $allowedOps = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL', 'IN'];
                    if (!in_array($op, $allowedOps, true)) {
                        continue;
                    }

                    if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                        $whereParts[] = "`{$col}` {$op}";
                        $previewWhereParts[] = "`{$col}` {$op}";
                    } elseif ($op === 'IN') {
                        $inVals = array_map('trim', explode(',', $val));
                        $inVals = array_values(array_filter($inVals, fn($v) => $v !== ''));
                        if (!empty($inVals)) {
                            $placeholders = implode(', ', array_fill(0, count($inVals), '?'));
                            $whereParts[] = "`{$col}` IN ({$placeholders})";
                            $previewWhereParts[] = "`{$col}` IN (" . implode(', ', array_map(fn($v) => "'" . addslashes($v) . "'", $inVals)) . ")";
                            foreach ($inVals as $iv) {
                                $params[] = $iv;
                            }
                        }
                    } else {
                        $whereParts[] = "`{$col}` {$op} ?";
                        $previewWhereParts[] = "`{$col}` {$op} '" . addslashes($val) . "'";
                        $params[] = $val;
                    }
                }
            }

            $sql = "SELECT * FROM `{$table}`";
            $previewSql = "SELECT * FROM `{$table}`";

            if (!empty($whereParts)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereParts);
                $previewSql .= ' WHERE ' . implode(' AND ', $previewWhereParts);
            }

            $sortCol = trim((string)($_POST['sort_column'] ?? ''));
            $sortDir = strtoupper(trim((string)($_POST['sort_direction'] ?? 'ASC')));
            if ($sortDir !== 'DESC') {
                $sortDir = 'ASC';
            }

            if ($sortCol !== '' && isset($schema['columns'][$sortCol])) {
                $sql .= " ORDER BY `{$sortCol}` {$sortDir}";
                $previewSql .= " ORDER BY `{$sortCol}` {$sortDir}";
            }

            $limit = (int)($_POST['limit'] ?? 100);
            if ($limit < 1) $limit = 100;
            if ($limit > 5000) $limit = 5000;

            $sql .= " LIMIT {$limit}";
            $previewSql .= " LIMIT {$limit}";

            $start = microtime(true);
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => $conn->error, 'sql' => $previewSql]);
                break;
            }

            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }

            $res = $stmt->get_result();
            $elapsedMs = round((microtime(true) - $start) * 1000, 2);

            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();

            $colNames = array_keys($schema['columns']);
            $hasPk = !empty($schema['primary_key']);

            echo json_encode([
                'success' => true,
                'sql' => $previewSql,
                'columns' => $colNames,
                'rows' => $rows,
                'count' => count($rows),
                'execution_time_ms' => $elapsedMs,
                'table' => $table,
                'primary_key' => $schema['primary_key'],
                'editable' => $hasPk
            ]);
            break;

        case 'dry_run':
            $rawQuery = trim((string)($_POST['query'] ?? ''));
            $forbidden = checkForbiddenKeywords($rawQuery);
            if ($forbidden) {
                echo json_encode(['success' => false, 'error' => "Opdracht '{$forbidden}' is niet toegestaan."]);
                break;
            }

            $keyword = getFirstKeyword($rawQuery);
            if (!in_array($keyword, ['UPDATE', 'DELETE', 'INSERT', 'REPLACE'], true)) {
                echo json_encode(['success' => false, 'error' => 'Dry-run is alleen van toepassing op schrijfopdrachten (UPDATE, DELETE, INSERT).']);
                break;
            }

            $conn->begin_transaction();
            $res = $conn->query($rawQuery);
            if (!$res) {
                $err = $conn->error;
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $err]);
                break;
            }
            $affected = $conn->affected_rows;
            $conn->rollback();

            echo json_encode(['success' => true, 'affected_rows' => $affected, 'query' => $rawQuery]);
            break;

        case 'run_manual':
            $rawQuery = trim((string)($_POST['query'] ?? ''));
            $confirmed = !empty($_POST['confirmed']) && ($_POST['confirmed'] === '1' || $_POST['confirmed'] === true || $_POST['confirmed'] === 'true');

            $forbidden = checkForbiddenKeywords($rawQuery);
            if ($forbidden) {
                echo json_encode(['success' => false, 'error' => "Opdracht '{$forbidden}' is geblokkeerd uit veiligheidsoverwegingen."]);
                break;
            }

            $keyword = getFirstKeyword($rawQuery);

            if (in_array($keyword, ['UPDATE', 'DELETE', 'INSERT', 'REPLACE'], true)) {
                if (!$confirmed) {
                    echo json_encode(['success' => false, 'requires_confirmation' => true, 'query' => $rawQuery]);
                    break;
                }

                $start = microtime(true);
                $res = $conn->query($rawQuery);
                if (!$res) {
                    echo json_encode(['success' => false, 'error' => $conn->error]);
                    break;
                }
                $affected = $conn->affected_rows;
                $elapsedMs = round((microtime(true) - $start) * 1000, 2);

                recordAuditLog(
                    $conn,
                    'database',
                    'manual_write',
                    "Handmatige {$keyword} uitgevoerd: {$rawQuery} ({$affected} rijen beïnvloed)",
                    [
                        'severity' => 'warning',
                        'metadata' => [
                            'query' => $rawQuery,
                            'affected_rows' => $affected,
                            'execution_time_ms' => $elapsedMs
                        ]
                    ]
                );

                echo json_encode([
                    'success' => true,
                    'write' => true,
                    'affected_rows' => $affected,
                    'execution_time_ms' => $elapsedMs
                ]);
                break;
            }

            if (!in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
                echo json_encode(['success' => false, 'error' => "Onbekend of niet ondersteund type query: '{$keyword}'"]);
                break;
            }

            $conn->query('START TRANSACTION READ ONLY');
            $start = microtime(true);
            $res = $conn->query($rawQuery);
            $elapsedMs = round((microtime(true) - $start) * 1000, 2);

            if (!$res) {
                $err = $conn->error;
                $conn->query('ROLLBACK');
                echo json_encode(['success' => false, 'error' => $err]);
                break;
            }

            $conn->query('ROLLBACK');

            $rows = [];
            $cols = [];
            if ($res instanceof mysqli_result) {
                while ($field = $res->fetch_field()) {
                    $cols[] = $field->name;
                }
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }

            $whitelistedTables = getWhitelistedTables($conn);
            $detectedTable = detectSingleTableQuery($rawQuery, $whitelistedTables);
            $isEditable = false;
            $primaryKey = [];

            if ($detectedTable) {
                $schema = getTableSchema($conn, $detectedTable);
                if ($schema && !empty($schema['primary_key'])) {
                    $allPkPresent = true;
                    foreach ($schema['primary_key'] as $pkCol) {
                        if (!in_array($pkCol, $cols, true)) {
                            $allPkPresent = false;
                            break;
                        }
                    }
                    if ($allPkPresent) {
                        $isEditable = true;
                        $primaryKey = $schema['primary_key'];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'write' => false,
                'columns' => $cols,
                'rows' => $rows,
                'count' => count($rows),
                'execution_time_ms' => $elapsedMs,
                'editable' => $isEditable,
                'editable_table' => $detectedTable,
                'primary_key' => $primaryKey
            ]);
            break;

        case 'update_cell':
            $table = trim((string)($_POST['table'] ?? ''));
            $column = trim((string)($_POST['column'] ?? ''));
            $rawPk = $_POST['pk'] ?? '{}';
            $pk = is_string($rawPk) ? json_decode($rawPk, true) : (array)$rawPk;
            $isNull = !empty($_POST['is_null']) && ($_POST['is_null'] === '1' || $_POST['is_null'] === true || $_POST['is_null'] === 'true');
            $value = $isNull ? null : (string)($_POST['value'] ?? '');

            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }

            if (!isset($schema['columns'][$column])) {
                echo json_encode(['success' => false, 'error' => "Kolom '{$column}' niet gevonden in tabel '{$table}'."]);
                break;
            }

            if (in_array($column, $schema['primary_key'], true)) {
                echo json_encode(['success' => false, 'error' => "Primaire sleutelkolom '{$column}' kan niet worden bewerkt."]);
                break;
            }

            if (empty($schema['primary_key'])) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' heeft geen primaire sleutel."]);
                break;
            }

            $whereParts = [];
            $whereParams = [];
            foreach ($schema['primary_key'] as $pkCol) {
                if (!isset($pk[$pkCol])) {
                    echo json_encode(['success' => false, 'error' => "Ontbrekende primaire sleutelwaarde voor '{$pkCol}'."]);
                    break 2;
                }
                $whereParts[] = "`{$pkCol}` = ?";
                $whereParams[] = $pk[$pkCol];
            }

            $selectSql = "SELECT `{$column}` FROM `{$table}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            $stmtOld = $conn->prepare($selectSql);
            $stmtOld->execute($whereParams);
            $oldRes = $stmtOld->get_result()->fetch_assoc();
            $stmtOld->close();
            $oldValue = $oldRes ? $oldRes[$column] : null;

            if ($isNull) {
                $updateSql = "UPDATE `{$table}` SET `{$column}` = NULL WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
                $updateParams = $whereParams;
            } else {
                $updateSql = "UPDATE `{$table}` SET `{$column}` = ? WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
                $updateParams = array_merge([$value], $whereParams);
            }

            $stmtUpd = $conn->prepare($updateSql);
            if (!$stmtUpd) {
                echo json_encode(['success' => false, 'error' => $conn->error]);
                break;
            }

            $stmtUpd->execute($updateParams);
            $stmtUpd->close();

            recordAuditLog(
                $conn,
                'database',
                'update_cell',
                "Cel bijgewerkt in {$table}.{$column} voor PK " . json_encode($pk),
                [
                    'metadata' => [
                        'table' => $table,
                        'column' => $column,
                        'old_value' => $oldValue,
                        'new_value' => $value,
                        'pk' => $pk
                    ]
                ]
            );

            echo json_encode(['success' => true]);
            break;

        case 'update_row':
            $table = trim((string)($_POST['table'] ?? ''));
            $rawPk = $_POST['pk'] ?? '{}';
            $pk = is_string($rawPk) ? json_decode($rawPk, true) : (array)$rawPk;
            $rawValues = $_POST['values'] ?? '{}';
            $values = is_string($rawValues) ? json_decode($rawValues, true) : (array)$rawValues;
            $rawNulls = $_POST['null_columns'] ?? '[]';
            $nullCols = is_string($rawNulls) ? json_decode($rawNulls, true) : (array)$rawNulls;

            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }

            $setParts = [];
            $setParams = [];

            foreach ($values as $col => $val) {
                if (!isset($schema['columns'][$col])) continue;
                if (in_array($col, $schema['primary_key'], true)) continue;

                $setParts[] = "`{$col}` = ?";
                $setParams[] = $val;
            }

            if (is_array($nullCols)) {
                foreach ($nullCols as $col) {
                    if (!isset($schema['columns'][$col])) continue;
                    if (in_array($col, $schema['primary_key'], true)) continue;

                    $setParts[] = "`{$col}` = NULL";
                }
            }

            if (empty($setParts)) {
                echo json_encode(['success' => false, 'error' => 'Geen kolommen om bij te werken.']);
                break;
            }

            $whereParts = [];
            $whereParams = [];
            foreach ($schema['primary_key'] as $pkCol) {
                if (!isset($pk[$pkCol])) {
                    echo json_encode(['success' => false, 'error' => "Ontbrekende primaire sleutelwaarde voor '{$pkCol}'."]);
                    break 2;
                }
                $whereParts[] = "`{$pkCol}` = ?";
                $whereParams[] = $pk[$pkCol];
            }

            $updateSql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            $stmtUpd = $conn->prepare($updateSql);
            if (!$stmtUpd) {
                echo json_encode(['success' => false, 'error' => $conn->error]);
                break;
            }

            $allParams = array_merge($setParams, $whereParams);
            $stmtUpd->execute($allParams);
            $stmtUpd->close();

            recordAuditLog(
                $conn,
                'database',
                'update_row',
                "Rij bijgewerkt in {$table} voor PK " . json_encode($pk),
                [
                    'metadata' => [
                        'table' => $table,
                        'updates' => $values,
                        'null_columns' => $nullCols,
                        'pk' => $pk
                    ]
                ]
            );

            echo json_encode(['success' => true]);
            break;

        case 'delete_row':
            $table = trim((string)($_POST['table'] ?? ''));
            $rawPk = $_POST['pk'] ?? '{}';
            $pk = is_string($rawPk) ? json_decode($rawPk, true) : (array)$rawPk;

            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }

            if (empty($schema['primary_key'])) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' heeft geen primaire sleutel."]);
                break;
            }

            $whereParts = [];
            $whereParams = [];
            foreach ($schema['primary_key'] as $pkCol) {
                if (!isset($pk[$pkCol])) {
                    echo json_encode(['success' => false, 'error' => "Ontbrekende primaire sleutelwaarde voor '{$pkCol}'."]);
                    break 2;
                }
                $whereParts[] = "`{$pkCol}` = ?";
                $whereParams[] = $pk[$pkCol];
            }

            $stmtOld = $conn->prepare("SELECT * FROM `{$table}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1");
            $stmtOld->execute($whereParams);
            $oldRow = $stmtOld->get_result()->fetch_assoc();
            $stmtOld->close();

            $delSql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            $stmtDel = $conn->prepare($delSql);
            if (!$stmtDel) {
                echo json_encode(['success' => false, 'error' => $conn->error]);
                break;
            }

            $stmtDel->execute($whereParams);
            $affected = $stmtDel->affected_rows;
            $stmtDel->close();

            recordAuditLog(
                $conn,
                'database',
                'delete_row',
                "Rij verwijderd uit {$table} met PK " . json_encode($pk),
                [
                    'severity' => 'warning',
                    'metadata' => [
                        'table' => $table,
                        'deleted_row' => $oldRow,
                        'pk' => $pk
                    ]
                ]
            );

            echo json_encode(['success' => true, 'affected_rows' => $affected]);
            break;

        case 'insert_row':
            $table = trim((string)($_POST['table'] ?? ''));
            $rawValues = $_POST['values'] ?? '{}';
            $values = is_string($rawValues) ? json_decode($rawValues, true) : (array)$rawValues;
            $rawNulls = $_POST['null_columns'] ?? '[]';
            $nullCols = is_string($rawNulls) ? json_decode($rawNulls, true) : (array)$rawNulls;

            $schema = getTableSchema($conn, $table);
            if (!$schema) {
                echo json_encode(['success' => false, 'error' => "Tabel '{$table}' niet gevonden."]);
                break;
            }

            $cols = [];
            $placeholders = [];
            $params = [];

            foreach ($values as $col => $val) {
                if (!isset($schema['columns'][$col])) continue;
                if ($schema['columns'][$col]['is_auto_increment'] && ($val === '' || $val === null)) {
                    continue;
                }

                $cols[] = "`{$col}`";
                $placeholders[] = '?';
                $params[] = $val;
            }

            if (is_array($nullCols)) {
                foreach ($nullCols as $col) {
                    if (!isset($schema['columns'][$col])) continue;
                    $cols[] = "`{$col}`";
                    $placeholders[] = 'NULL';
                }
            }

            if (empty($cols)) {
                echo json_encode(['success' => false, 'error' => 'Geen geldige kolommen opgegeven voor invoegen.']);
                break;
            }

            $insertSql = "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmtIns = $conn->prepare($insertSql);
            if (!$stmtIns) {
                echo json_encode(['success' => false, 'error' => $conn->error]);
                break;
            }

            if (!empty($params)) {
                $stmtIns->execute($params);
            } else {
                $stmtIns->execute();
            }

            $insertId = $conn->insert_id;
            $stmtIns->close();

            $insertedRow = null;
            if ($insertId > 0 && count($schema['primary_key']) === 1 && $schema['columns'][$schema['primary_key'][0]]['is_auto_increment']) {
                $pkCol = $schema['primary_key'][0];
                $sFetch = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1");
                $sFetch->execute([$insertId]);
                $insertedRow = $sFetch->get_result()->fetch_assoc();
                $sFetch->close();
            } elseif (!empty($schema['primary_key'])) {
                $whereParts = [];
                $whereParams = [];
                foreach ($schema['primary_key'] as $pkCol) {
                    $pkVal = $values[$pkCol] ?? ($pkCol === $schema['primary_key'][0] ? $insertId : null);
                    if ($pkVal !== null) {
                        $whereParts[] = "`{$pkCol}` = ?";
                        $whereParams[] = $pkVal;
                    }
                }
                if (count($whereParts) === count($schema['primary_key'])) {
                    $sFetch = $conn->prepare("SELECT * FROM `{$table}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1");
                    $sFetch->execute($whereParams);
                    $insertedRow = $sFetch->get_result()->fetch_assoc();
                    $sFetch->close();
                }
            }

            recordAuditLog(
                $conn,
                'database',
                'insert_row',
                "Nieuwe rij ingevoegd in {$table}",
                [
                    'metadata' => [
                        'table' => $table,
                        'inserted_data' => $values,
                        'null_columns' => $nullCols,
                        'insert_id' => $insertId
                    ]
                ]
            );

            echo json_encode([
                'success' => true,
                'insert_id' => $insertId,
                'row' => $insertedRow
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Onbekende actie: '{$action}'"]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Databasefout: ' . $e->getMessage()
    ]);
}
