#!/bin/bash

echo "🤖 [Audit 5/5: Icon Standard] Memeriksa staged changes..."

# Cek apakah ada file komponen/halaman yang di-stage
STAGED_DIFF=$(git diff --cached -- "app/**" "components/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Icon Standard] Tidak ada perubahan komponen/halaman yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << EOF > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Icon Standard.
Periksa Git Diff berikut HANYA terhadap Aturan Penggunaan Ikon:

Aturan Penggunaan Ikon:
1. MANDATORY LUCIDE-REACT:
   - Seluruh ikon visual WAJIB meng-import dan menggunakan pustaka `lucide-react` (seperti `<Plus size={16} />`, `<Trash2 size={16} />`, `<Filter size={16} />`, dsb.).

2. DILARANG SVG MENTAH INLINE & ICON THIRD-PARTY NON-STANDAR:
   - DILARANG KERAS menyisipkan tag `<svg>` mentah inline dengan `<path>` panjang di file komponen jika ikon sudah tersedia di `lucide-react`.
   - DILARANG KERAS menggunakan `<i className="fa ...">` (FontAwesome legacy) atau meng-import pustaka ikon pihak ketiga lainnya.

Git Diff yang di-stage:
\`\`\`diff
$STAGED_DIFF
\`\`\`

Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi Icon Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan penggunaan ikon non-standar / SVG mentah.
EOF

RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Icon Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Icon Standard] PASSED."
    exit 0
fi
