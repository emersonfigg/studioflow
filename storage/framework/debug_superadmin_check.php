<?php

$db = new PDO('sqlite:' . __DIR__ . '/../../database/database.sqlite');

$target = $db->query("select id,name,email,company_id,role,global_role,password from users where email='superadmin@studioflow.local'")
    ->fetchAll(PDO::FETCH_ASSOC);

$sample = $db->query("select id,name,email,company_id,role,global_role from users order by id limit 10")
    ->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'target' => $target,
    'sample' => $sample,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
