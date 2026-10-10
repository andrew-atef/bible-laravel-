<?php
$pdo = new PDO('sqlite:database/biblical_core_new.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $pdo->query("SELECT id, code, testament FROM books ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
echo "--- raw hex testament for id=1: ".bin2hex($pdo->query("SELECT testament FROM books WHERE id=1")->fetchColumn())."\n";
$pdo->exec("UPDATE books SET testament='OT' WHERE id BETWEEN 1 AND 39");
$pdo->exec("UPDATE books SET testament='NT' WHERE id BETWEEN 40 AND 66");
echo "Fixed\n";
foreach ($pdo->query("SELECT id, code, testament FROM books WHERE id IN (1,39,40,66)")->fetchAll(PDO::FETCH_ASSOC) as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
