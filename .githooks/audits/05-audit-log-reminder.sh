#!/bin/bash
#
# Audit 6/9: Audit Log Observer reminder (non-blocking).
# Mengingatkan saat ada Model baru: daftarkan Observer + AuditLogService
# untuk data sensitif (lihat .agent/skills/audit_log_standard/SKILL.md).

echo "📝 [Audit 6/9: Audit Log Observer] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=A -- "app/Models/**/*.php" "app/Models/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit Audit Log] Tidak ada Model baru. Skip."
    exit 0
fi

echo "⚠️ [Audit Audit Log] Model baru terdeteksi:"
echo "$STAGED_FILES" | sed 's/^/    /'
echo "   💡 Untuk data sensitif: buat Observer (Create/Update/Delete -> AuditLogService) dan daftarkan di AppServiceProvider."
echo "✅ [Audit Audit Log] PASSED (pengingat saja)."
exit 0
