#!/bin/bash

echo "🤖 [Audit 4/4: State Management Standard] Memeriksa staged changes..."

# Cek apakah ada file store atau state management yang di-stage
STAGED_DIFF=$(git diff --cached -- "store/**" "hooks/**" "app/(main)/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit State Management Standard] Tidak ada perubahan state/store yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << EOF > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus State Management Standard.
Periksa Git Diff berikut HANYA terhadap Aturan State Management:

Aturan State Management:
1. ZUSTAND GLOBAL STORE:
   - Global state WAJIB menggunakan Zustand dan berlokasi di `@/store/` dengan TypeScript interface terpisah untuk State dan Actions.

2. LOCAL STATE SCOPING:
   - Data transient/spesifik 1 halaman (seperti baris tabel CRUD) DILARANG dimasukkan ke Zustand global store. Wajib dikelola dalam local state (`useState` / React Query).

3. PERSIST MIDDLEWARE:
   - State yang memerlukan persistensi (seperti auth session / ui preferences) WAJIB menggunakan middleware `persist` dengan atribut `name` unik.

Git Diff yang di-stage:
\`\`\`diff
$STAGED_DIFF
\`\`\`

Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi State Management Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan pelanggaran State Management Standard.
EOF

RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit State Management Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit State Management Standard] PASSED."
    exit 0
fi
