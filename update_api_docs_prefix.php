<?php

function replaceInFile($file, $search, $replace) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = str_replace($search, $replace, $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}

// 1. AuditLogController
replaceInFile('docs/api/IAM/AuditLogController.md', '/api/audit-logs', '/api/admin/audit-logs');

// 2. RoleController
replaceInFile('docs/api/IAM/RoleController.md', '/api/roles', '/api/admin/roles');

// 3. UserController
replaceInFile('docs/api/IAM/UserController.md', '/api/users', '/api/admin/users');

echo "Done updating prefixes.\n";
