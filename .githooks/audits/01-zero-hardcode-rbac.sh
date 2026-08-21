#!/bin/bash

echo "🤖 [Audit 1/7: Zero Hardcode & RBAC] Memeriksa staged changes..."

STAGED_DIFF=$(git diff --cached)

if [ -z "$STAGED_DIFF" ]; then
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Zero Hardcode & RBAC.
Periksa Git Diff berikut HANYA terhadap aturan Zero Hardcode & RBAC Policy:

Aturan:
1. DILARANG KERAS MENGGUNAKAN VALIDASI ENUM STATIS: Seluruh form request/controller yang memvalidasi input *dropdown* (seperti `in:REGULER,KARYAWAN` atau `in:Islam,Kristen`) wajib menggunakan aturan dinamis `exists:nama_tabel,id` dan datanya wajib bersumber dari tabel database (misalnya `master_referensi` atau tabel master lain).
2. DILARANG MENYEDIAKAN ARRAY/ENUM LITERAL STATIS: Jangan ada *array literal* di Controller atau Model untuk pilihan statis jika pilihan tersebut merepresentasikan data master referensi.
3. DILARANG ADA HARDCODE string nama modul/role (seperti 'spmb', 'sikeu', 'admin', 'mahasiswa') dalam pengujian logika IF/ELSE atau perbandingan statis.
4. DILARANG menggunakan properti statis user.user_type atau user_type.
5. Seluruh otorisasi dan relasi WAJIB berbasis ID entitas atau hook RBAC (seperti hasRole / hasPermission).

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika kode bersih dari hardcode dan sesuai RBAC.
- REJECTED: [detail alasan pelanggaran] jika ditemukan hardcode/pelanggaran RBAC.
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
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Zero Hardcode & RBAC] PASSED."
    exit 0
fi
