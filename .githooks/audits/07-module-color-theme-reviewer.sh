#!/bin/bash

echo "🤖 [Audit 7/7: Module Primary Color & Theme Standard] Memeriksa staged changes..."

# Cek apakah ada file komponen/halaman/sidebar/navigation yang di-stage
STAGED_DIFF=$(git diff --cached -- "app/**" "components/**" "services/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Module Color Theme Standard] Tidak ada perubahan komponen/halaman yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Module Primary Color & Theme Standard.
Periksa Git Diff berikut HANYA terhadap Aturan Keselarasan Warna Primary Modul & Menu:

Aturan Warna Primary & Tema Modul:
1. DYNAMIC MODULE COLOR BINDING:
   - Setiap modul dan menu di dalamnya WAJIB memanfaatkan `primary_color` dari entitas modul (misal via CSS variable `--module-primary`, theme context, atau dynamic style attribute).
   - DILARANG mengandalkan warna biru statis bawaan (`bg-blue-600` / `#3b82f6` hardcoded) secara membabi buta di seluruh modul.

2. ACTIVE MENU & ACCENT STYLING:
   - Indikator menu aktif, header modul, badge modul, dan aksen UI di dalam modul WAJIB secara dinamis mengikuti warna primary modul yang disetting pada database master modul.

3. DILARANG COLOR HARDCODING PER MODUL:
   - DILARANG KERAS meng-hardcode warna aksen modul secara statis pada file halaman/komponen jika modul tersebut telah memiliki `primary_color` terdaftar di database master modul (`/admin/modules`).

Git Diff yang di-stage:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi Module Primary Color & Theme Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan hardcode warna aksen modul statis / abaikan primary_color modul.
EOF

RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Module Color Theme Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Module Color Theme Standard] PASSED."
    exit 0
fi
