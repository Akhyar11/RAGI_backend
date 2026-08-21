#!/bin/bash

echo "🤖 [Audit 3/7: Form & Input Validation Standard] Memeriksa staged changes..."

# Cek apakah ada file form / input yang di-stage
STAGED_DIFF=$(git diff --cached -- "app/(main)/**" "components/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Form Validation Standard] Tidak ada perubahan komponen form/input yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Form Validation & Input Component Standard.
Periksa Git Diff berikut HANYA terhadap Aturan Form & Input Validation:

Aturan Form & Validasi:
1. KONSISTENSI UI KIT INPUT:
   - Komponen input form WAJIB menggunakan UI Kit terpusat (`<Input>`, `<Select>`, `<AsyncSelect>`, `<Textarea>`, `<Checkbox>`).
   - DILARANG menggunakan elemen HTML mentah (`<input>`, `<select>`, `<textarea>`) tanpa wrapper/style UI Kit.

2. SERVER-SIDE ASYNCSELECT UNTUK DATA API:
   - Jika dropdown/select mengambil data dari API backend (seperti User, Role, Pegawai, Matkul), WAJIB menggunakan `<AsyncSelect />` (`@/components/ui/AsyncSelect`) yang mendukung pencarian & fetching server-side.

3. MANDATORY STRICT ZOD VALIDATION:
   - DILARANG KERAS ada form tanpa validasi.
   - Seluruh form WAJIB menggunakan skema Zod (`z.object({...})`) yang dihubungkan ke `react-hook-form` (`zodResolver(schema)`).
   - Error message wajib Bahasa Indonesia dan tampil di bawah field.

Git Diff yang di-stage:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi Form Validation Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan pelanggaran Form Validation Standard.
EOF

RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Form Validation Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Form Validation Standard] PASSED."
    exit 0
fi
