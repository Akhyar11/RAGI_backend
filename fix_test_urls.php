<?php

$files = [
    'tests/Feature/AuditLogTest.php' => ['/api/audit-logs', '/api/admin/audit-logs'],
    'tests/Feature/PermissionControllerTest.php' => ['/api/permissions', '/api/admin/permissions'],
    'tests/Feature/RoleControllerTest.php' => ['/api/roles', '/api/admin/roles'],
    'tests/Feature/UserCrudTest.php' => ['/api/users', '/api/admin/users'],
];

foreach ($files as $file => $replacements) {
    $content = file_get_contents($file);
    $content = str_replace($replacements[0], $replacements[1], $content);
    file_put_contents($file, $content);
}

echo "Done replacing URLs in tests.";
