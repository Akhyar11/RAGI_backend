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

Catatan Penting:
- HANYA periksa baris-baris kode baru yang DITAMBAHKAN atau DIUBAH (diawali tanda `+`). JANGAN menolak baris konteks yang tidak diubah.
- Status alur/workflow tiket atau entitas (seperti draft, submitted, dilaporkan, dalam_perbaikan, selesai, dibatalkan) merupakan status internal tabel dan BUKAN pelanggaran master referensi.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika kode bersih dari hardcode dan sesuai RBAC.
- REJECTED: [detail alasan pelanggaran] jika ditemukan hardcode/pelanggaran RBAC pada baris baru (+).
EOF

if command -v opencode &> /dev/null; then
    RESULT=$(timeout 25s opencode run -m opencode/muse-spark-1.3-contributor-free "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
elif command -v agy &> /dev/null; then
    RESULT=$(timeout 15s agy --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
else
    AI_EXIT_CODE=127
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "⚠️ [Audit Zero Hardcode & RBAC] AI tool timeout/gagal, dilewati."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Zero Hardcode & RBAC] PASSED."
    exit 0
fi
