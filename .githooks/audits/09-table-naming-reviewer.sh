#!/bin/bash

echo "🤖 [Audit 9/9: Table Naming Standard] Memeriksa staged changes..."

STAGED_DIFF=$(git diff --cached)

if [ -z "$STAGED_DIFF" ]; then
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Table Naming Standard.
Periksa Git Diff berikut HANYA terhadap aturan Table Naming Standard:

Aturan:
1. SEMUA nama table baru yang dibuat di dalam file Migration (`Schema::create(...)`) WAJIB diawali dengan nama modulnya (contoh: `sikeu_`, `spmb_`, `simpeg_`, `sinapra_`, `siakad_`, `core_`, dll). DILARANG menggunakan nama tabel tunggal tanpa prefix modul (seperti `tarif_ukt` atau `mahasiswa`).
2. SEMUA relasi foreign key pada migration (`constrained('nama_tabel')`) WAJIB mengarah pada tabel yang memiliki prefix modul.
3. JIKA ada Model baru yang dibuat atau dimodifikasi, property `protected $table` harus mereferensikan tabel dengan prefix modul. Jika tidak dispesifikasikan secara eksplisit (mengandalkan pluralisasi default Laravel), pastikan apakah nama Model tersebut sudah otomatis mematuhi aturan prefix modul (meski biasanya butuh didesain eksplisit `$table = 'modul_nama'`).
4. JIKA tidak ada penambahan tabel atau perubahan nama tabel di git diff, abaikan dan jawab PASSED.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika nama tabel/model sudah mematuhi standar prefix modul.
- REJECTED: [detail alasan pelanggaran dan tunjukkan nama tabel mana yang salah] jika ditemukan pembuatan tabel/relasi tanpa prefix modul.
EOF

if command -v agy &> /dev/null; then
    RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
    AGY_EXIT_CODE=$?
else
    AGY_EXIT_CODE=127
fi

if [ $AGY_EXIT_CODE -ne 0 ]; then
    echo "⚠️ [Fallback] agy gagal atau tidak ditemukan. Beralih ke opencode (9router/combo)..."
    RESULT=$(opencode run -m 9router/combo "$(cat "$PROMPT_FILE")" 2>&1)
fi
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Table Naming Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Table Naming Standard] PASSED."
    exit 0
fi
