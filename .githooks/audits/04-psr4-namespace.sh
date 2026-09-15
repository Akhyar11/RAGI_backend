#!/bin/bash
#
# Audit 5/9: PSR-4 Namespace (deterministik, tanpa AI).
# File PHP baru di app/ WAJIB mendeklarasikan namespace yang sesuai path-nya
# (mis. app/Services/IAM/Foo.php -> namespace App\Services\IAM;).

echo "📦 [Audit 5/9: PSR-4 Namespace] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=A -- "app/**/*.php" "app/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit PSR-4 Namespace] Tidak ada file PHP baru di app/. Skip."
    exit 0
fi

FAILED=0

while IFS= read -r file; do
    [ -f "$file" ] || continue
    # app/Services/IAM/Foo.php -> App\Services\IAM
    EXPECTED=$(dirname "$file" | sed 's|^app|App|' | tr '/' '\\')
    DECLARED=$(grep -m1 -oP '^namespace\s+\K[^;]+' "$file" || true)
    if [ -z "$DECLARED" ]; then
        echo "❌ [Audit PSR-4] $file tidak mendeklarasikan namespace."
        FAILED=1
    elif [ "$DECLARED" != "$EXPECTED" ]; then
        echo "❌ [Audit PSR-4] Namespace $file salah: '$DECLARED', seharusnya '$EXPECTED'."
        FAILED=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit PSR-4 Namespace] PASSED."
exit 0
