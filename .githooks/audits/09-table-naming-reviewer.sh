#!/bin/bash

echo "🗄️ [Audit 9/9: Table Naming Standard] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/migrations/**" "app/Models/**")
else
    STAGED_DIFF=$(git diff --cached -- "database/migrations/**" "app/Models/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Table Naming Standard] Tidak ada perubahan migration atau model yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Table Naming Standard.
Periksa Git Diff berikut HANYA terhadap aturan Table Naming Standard:

Aturan Table Naming Standard:
1. PREFIX MODUL WAJIB PADA SCHEMA::CREATE:
   - SEMUA nama tabel baru yang dibuat di migration (`Schema::create('nama_tabel', ...)`) WAJIB diawali dengan nama modulnya (contoh prefix: `core_`, `spmb_`, `siakad_`, `sikeu_`, `simpeg_`, `sinapra_`, `sippm_`, `lms_`, `oauth_`, `sso_`). DILARANG menggunakan nama tabel tunggal tanpa prefix modul (seperti `users`, `mahasiswa`, `tarif`).
2. FOREIGN KEY WAJIB KE TABEL BERPREFIX:
   - SEMUA relasi foreign key pada migration (`constrained('nama_tabel')`) WAJIB mengarah pada tabel yang memiliki prefix modul yang sah.
3. MODEL PROPERTY $TABLE:
   - Model baru atau dimodifikasi harus mendefinisikan property `protected $table` yang mereferensikan nama tabel berprefix modul.

Catatan Penting:
- HANYA periksa baris-baris kode baru yang DITAMBAHKAN atau DIUBAH (diawali tanda `+`). JANGAN menolak baris konteks yang tidak diubah.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$TRUNCATED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika nama tabel/model sudah mematuhi standar prefix modul.
- REJECTED: [detail alasan pelanggaran dan tunjukkan nama tabel yang salah] jika ditemukan tabel tanpa prefix modul pada baris baru (+).
EOF

if [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 30s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
elif command -v agy &> /dev/null; then
    RESULT=$(timeout 20s agy --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
else
    AI_EXIT_CODE=127
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "⚠️ [Audit Table Naming Standard] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Table Naming Standard] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Table Naming Standard] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
