#!/bin/bash

echo "🤖 [Audit 3/9: API CRUD Standard] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Http/Controllers/**" "app/Http/Requests/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/Http/Controllers/**" "app/Http/Requests/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API CRUD Standard] Tidak ada perubahan controller/request yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API CRUD Standard Laravel.
Periksa Git Diff berikut HANYA terhadap Aturan Standar API CRUD:

Aturan:
1. FORM REQUEST WAJIB (NO INLINE VALIDATION):
   - Validasi data input WAJIB menggunakan Form Request terpisah di `app/Http/Requests/` (contoh: `StoreItemRequest`, `UpdateItemRequest`).
   - DILARANG memanggil validasi inline langsung seperti `$request->validate([...])` di dalam Controller.
2. SERVER-SIDE PAGINATION PADA INDEX:
   - Endpoint listing/index di Controller WAJIB menggunakan `paginate(...)` untuk mendukung limit & pagination. Dilarang `->get()` atau `->all()` tanpa limitasi pada data tabel dinamis.
3. KONSISTENSI RESPONSE JSON:
   - Format response JSON harus memiliki envelope konsisten (`status`, `message`, `data`).

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
- PASSED jika kode bersih dan memenuhi API CRUD Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan pelanggaran API CRUD Standard pada baris baru (+).
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
    echo "⚠️ [Audit API CRUD Standard] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API CRUD Standard] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit API CRUD Standard] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
