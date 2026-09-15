#!/bin/bash
#
# Audit 8/9: Seeder reminder (non-blocking).
# Mengingatkan saat ada migration baru: pastikan seeder diperbarui bila
# tabel butuh data awal (lihat .agent/skills/seeder_standard/SKILL.md).

echo "🌱 [Audit 8/9: Seeder Reminder] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "database/migrations/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit Seeder] Tidak ada migration yang di-stage. Skip."
    exit 0
fi

echo "⚠️ [Audit Seeder] Migration baru/diubah terdeteksi:"
echo "$STAGED_FILES" | sed 's/^/    /'
echo "   💡 Pastikan seeder diperbarui bila tabel butuh data awal, dan down() membalik urutan up()."
echo "✅ [Audit Seeder] PASSED (pengingat saja)."
exit 0
