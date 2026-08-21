#!/bin/bash

echo "🤖 [Audit 6/7: Spacing, Margin & Padding Standard] Memeriksa staged changes..."

# Cek apakah ada file komponen/halaman/CSS yang di-stage
STAGED_DIFF=$(git diff --cached -- "app/**" "components/**" "styles/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Spacing Standard] Tidak ada perubahan komponen/halaman yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Spacing, Margin, Padding & Layout Alignment Standard.
Periksa Git Diff berikut HANYA terhadap Aturan Spacing & Layout Alignment:

Aturan Spacing, Margin & Padding:
1. SKALA SPACING STANDAR:
   - Penataan jarak WAJIB menggunakan skala standar Tailwind/Design System (`p-2` s.d `p-6`, `m-2` s.d `m-6`, `gap-2` s.d `gap-6`, `space-y-4` s.d `space-y-6`).
   - DILARANG HARDBOUND/ARBITRARY PIXEL SPACING seperti `m-[37px]`, `p-[19px]`, `gap-[13px]`, `mt-[42px]`, `px-[55px]`, `py-[33px]`.

2. COMPACT & PROPORSIONAL CARD PADDING:
   - Wadah utama (Card, Modal, Drawer, Table Container) wajib efisien (`p-4` atau `p-6` di desktop, `p-3` atau `p-4` di mobile).
   - DILARANG OVERSIZED PADDING seperti `p-16`, `p-20`, `px-24` yang membuang area layar.

3. KONSISTENSI GAP FLEX/GRID & ALIGNMENT:
   - Jarak antarelemen di container flexbox/grid wajib menggunakan `gap-*` (misal `gap-4` / `gap-6`).
   - DILARANG mencampur `gap` dengan inline margin manual (`mt-*`, `mb-*`, `ml-*`, `mr-*`) di elemen turunan flexbox/grid yang dapat merusak alignment.

4. OUTER SECTION SPACING:
   - Jarak vertikal antar section/card utama halaman wajib rapi dengan `space-y-4` / `space-y-6` atau `mb-4` / `mb-6`.
   - Dilarang menggunakan margin negatif acak (seperti `-mt-20`) tanpa kebutuhan overlay UI khusus.

Git Diff yang di-stage:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi Spacing, Margin & Padding Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan penggunaan arbitrary pixel spacing / oversized padding / layout misalignment.
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
    echo "❌ [Audit Spacing Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Spacing Standard] PASSED."
    exit 0
fi
