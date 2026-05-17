<?php
// Configuration
$sqliteFile = 'database/database.sqlite';
$outputFile = 'data_export.sql';

try {
    $db = new PDO("sqlite:$sqliteFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'migrations'")->fetchAll(PDO::FETCH_COLUMN);

    $sqlDump = "SET FOREIGN_KEY_CHECKS = 0;\n";

    foreach ($tables as $table) {
        $rows = $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) continue;

        $sqlDump .= "\n-- Data for table $table\n";
        foreach ($rows as $row) {
            $columns = implode("`, `", array_keys($row));
            $values = array_map(function($val) {
                if ($val === null) return 'NULL';
                return "'" . str_replace("'", "''", $val) . "'";
            }, array_values($row));
            $valuesList = implode(", ", $values);
            $sqlDump .= "INSERT INTO `$table` (`$columns`) VALUES ($valuesList);\n";
        }
    }

    $sqlDump .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents($outputFile, $sqlDump);
    echo "Export terminé ! Fichier généré : $outputFile\n";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
