#!/bin/bash

echo "🤖 [Audit 2/7: Admin CRUD Standard] Memeriksa staged changes..."

# Cek apakah ada file halaman admin/CRUD yang di-stage
STAGED_DIFF=$(git diff --cached -- "app/(main)/**" "components/**")

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Admin CRUD Standard] Tidak ada perubahan pada file admin/CRUD yang di-stage. Skip."
    exit 0
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Admin CRUD Standard.
Periksa Git Diff berikut HANYA terhadap Aturan Admin CRUD & Table Standard:

Aturan Admin CRUD:
1. MOBILE-FIRST RESPONSIVE STYLING:
   - Layout dan halaman WAJIB menggunakan pendekatan Mobile-First (misal: `w-full flex-col grid-cols-1 gap-4`) dengan breakpoint responsif (`sm:`, `md:`, `lg:`).

2. HALAMAN DETAIL TERPISAH (SEPARATE DETAIL PAGE):
   - Tampilan Detail data/rincian entitas WAJIB dibuat di Halaman Terpisah (route `/[id]` atau `/detail/[id]`) dengan Tombol Kembali Oranye di `PageHeader`. DILARANG menjejalkan detail rumit ke dalam modal kecil.

3. DESAIN FORM COMPACT & ELEGAN:
   - Form harus dirancang sangat compact, rapi, dan proporsional (grid 1 kolom di mobile, max 2-3 kolom di desktop: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`). No excessive whitespace or huge margins.

4. ATOMIC DESIGN ARCHITECTURE:
   - Menggunakan komponen dari `@/components/ui/` (Button, Input, Select, Modal, Drawer, DataTable, Badge) dan `@/components/layout/` (PageHeader).
   - DILARANG menggunakan elemen HTML mentah tanpa style bawaan (seperti tag <table> mentah atau <select> mentah).

5. DATATABLE & API PAGINATION:
   - Jika halaman berupa list/tabel data, WAJIB menggunakan `<DataTable />` dari `@/components/ui/DataTable`.
   - Data WAJIB diambil dari API dengan server-side pagination (`page`, `limit`) dan prop `meta={meta}`.

6. SORT BY & SORT DIRECTION (DEFAULT NAME/LABEL):
   - Halaman list/tabel WAJIB memiliki opsi `sort_by` / `orderBy` (default berbasis `name` / `label` / `id`) dan `sort_dir` / `orderDir` (`asc` / `desc`).
   - Opsi pengurutan diletakkan di dalam Drawer dengan layout grid 2 kolom.

7. TOMBOL FILTER OUTLINE BIRU & DRAWER SLIDE KANAN-KE-KIRI:
   - Tombol Filter WAJIB bertipe outline biru (`variant="outline"` / ikon `<Filter size={16} />`).
   - Membuka panel `<Drawer />` yang meluncur dari kanan ke kiri (*right-to-left*).

8. FORM & LAYOUT CONSISTENCY:
   - Form <= 5 inputs: Gunakan Modal (`<Modal />`) dengan grid maksimal 2 kolom (`grid grid-cols-1 md:grid-cols-2 gap-4`).
   - Form > 5 inputs: Gunakan Halaman Terpisah dengan Tombol Kembali Berwarna Oranye (`bg-orange-500 text-white`) di `PageHeader`.

9. WAJIB 3-DOTS ACTION DROPDOWN MENU (<DropdownMenu />):
   - Seluruh aksi tabel (Edit, Hapus, Detail, dll.) WAJIB menggunakan menu titik 3 (`<DropdownMenu />` dari `@/components/ui/DropdownMenu`).
   - DILARANG KERAS menyejajarkan tombol-tombol aksi secara horizontal di sel tabel (*inefficient space*).

Git Diff yang di-stage:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi Admin CRUD Standard.
- REJECTED: [detail alasan pelanggaran] jika ditemukan pelanggaran Admin CRUD Standard.
EOF

RESULT=$(agy --print "$(cat "$PROMPT_FILE")" 2>&1)
rm -f "$PROMPT_FILE"

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Admin CRUD Standard] REJECTED!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Admin CRUD Standard] PASSED."
    exit 0
fi
