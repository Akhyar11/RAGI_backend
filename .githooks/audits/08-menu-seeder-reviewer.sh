#!/bin/bash
# ==============================================================================
# AUDIT 08: Menu Seeder Organization Standard Reviewer (BE) — Deterministic & AI Strict
# Memastikan seeder menu (MenuSeeder.php) terorganisasi rapi:
# 1. Struktur Hirarki & Section Header UPPERCASE dengan anchor #
# 2. Menu leaf dengan URL unik dan valid (diawali /)
# 3. Urutan order_index sekuensial dan tidak bentrok
# 4. Modul konsisten antara parent section dan children
# 5. Ikon relevan & terdaftar di pemetaan ikon frontend (Sidebar.tsx)
# 6. Dilarang penumpukan menu acak/flat dump dengan ikon generik FaList
# ==============================================================================

echo "📋 [Audit 8/9: Menu Seeder Organization Standard] Memeriksa struktur dan kerapian seeder menu..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

if [ -n "$DIFF_TARGET" ]; then
    TARGET_FILES=$(git diff "$DIFF_TARGET" --name-only -- "database/seeders/**/*Menu*Seeder*.php" "database/seeders/**/Menu*.php")
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/seeders/**/*Menu*Seeder*.php" "database/seeders/**/Menu*.php")
else
    TARGET_FILES=$(git diff --cached --name-only -- "database/seeders/**/*Menu*Seeder*.php" "database/seeders/**/Menu*.php")
    STAGED_DIFF=$(git diff --cached -- "database/seeders/**/*Menu*Seeder*.php" "database/seeders/**/Menu*.php")
fi

IS_MANUAL_RUN=false
if [ -z "$TARGET_FILES" ] && [ -z "$STAGED_DIFF" ]; then
    if [ -t 0 ] || [ "$1" == "--all" ] || [ -z "$GIT_INDEX_FILE" ]; then
        IS_MANUAL_RUN=true
        TARGET_FILES=$(find database/seeders -type f -name "*Menu*Seeder*.php" 2>/dev/null)
    fi
fi

if [ -z "$TARGET_FILES" ] && [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Menu Seeder Organization] Tidak ada perubahan seeder menu yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. DETERMINISTIC STATIC VALIDATION VIA PHP CLI
# ------------------------------------------------------------------------------
STATIC_CHECK_RESULT=$(php -r '
$files = array_slice($argv, 1);
$violations = [];

// Daftar ikon yang didukung di frontend (Sidebar.tsx)
$supportedIcons = [
    "FaHome", "FaUserPlus", "FaChartPie", "FaUsers", "FaUserCheck", "FaList", "FaShieldAlt", "FaShield",
    "FaFileAlt", "FaClipboardCheck", "FaFileCheck", "FaCreditCard", "FaBookOpen", "FaAward", "FaLayers",
    "FaBoxes", "FaCalendar", "FaTrophy", "FaBriefcase", "FaClock", "FaSitemap", "FaMoneyBillWave",
    "FaCalendarCheck", "FaBuilding", "FaWrench", "FaShoppingCart", "FaUser", "FaSmartphone", "FaShieldCheck",
    "FaLock", "FaKey", "FaGraduationCap", "FaUserGraduate", "FaChalkboardTeacher", "FaExchangeAlt", "FaPen",
    "FaSyncAlt", "FaCloudUploadAlt", "FaDatabase", "FaTags", "FaSlidersH", "FaSliders", "FaBars", "FaMenu",
    "FaDesktop", "FaLaptop", "FaMonitor", "FaHistory", "FaCogs", "FaCog", "FaChartBar", "FaSparkles",
    "FaHourglassHalf", "FaFileSignature", "FaCheckSquare", "FaDollarSign", "FaExclamationTriangle"
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $menus = [];
    $modSpmb = "spmb";

    // Ekstrak array $menus atau $spmbMenus
    if (preg_match("/\\\$menus\s*=\s*(\\[[\\s\\S]*?\\];)/", $content, $m)) {
        eval("\$menus = " . $m[1]);
    } elseif (preg_match("/\\\$spmbMenus\s*=\s*(\\[[\\s\\S]*?\\];)/", $content, $m)) {
        eval("\$menus = " . $m[1]);
    }

    if (empty($menus)) continue;

    $urlsByModule = [];
    $genericIconCount = 0;
    $totalMenuCount = 0;

    foreach ($menus as $menu) {
        $totalMenuCount++;
        $name = $menu["name"] ?? "";
        $url = $menu["url"] ?? "";
        $icon = $menu["icon"] ?? "";
        $module = $menu["module"] ?? "";
        $order = $menu["order_index"] ?? null;
        $children = $menu["children"] ?? null;

        // Validasi modul
        $validModules = ["sso", "siakad", "simpeg", "sinapra", "sippm", "spmb", "sikeu"];
        if (!in_array($module, $validModules)) {
            $violations[] = [
                "file" => $file,
                "menu" => $name,
                "reason" => "Modul \"$module\" tidak valid. Harus salah satu dari: " . implode(", ", $validModules)
            ];
        }

        // Validasi order_index
        if ($order === null || !is_int($order) || $order < 1) {
            $violations[] = [
                "file" => $file,
                "menu" => $name,
                "reason" => "Menu \"$name\" wajib memiliki order_index berupa integer positif."
            ];
        }

        // Validasi icon
        if (!empty($icon) && !in_array($icon, $supportedIcons)) {
            $violations[] = [
                "file" => $file,
                "menu" => $name,
                "reason" => "Ikon \"$icon\" tidak terdaftar di pemetaan Sidebar frontend (iconMap)."
            ];
        }

        if ($icon === "FaList") {
            $genericIconCount++;
        }

        // Section header vs Leaf menu
        if (str_starts_with($url, "#")) {
            if (empty($children) || !is_array($children)) {
                $violations[] = [
                    "file" => $file,
                    "menu" => $name,
                    "reason" => "Section header \"$name\" ($url) wajib memiliki array children berisi sub-menu."
                ];
            }
            if ($name !== mb_strtoupper($name)) {
                $violations[] = [
                    "file" => $file,
                    "menu" => $name,
                    "reason" => "Nama section header \"$name\" wajib berhuruf kapital (UPPERCASE)."
                ];
            }
        } else {
            if (!str_starts_with($url, "/")) {
                $violations[] = [
                    "file" => $file,
                    "menu" => $name,
                    "reason" => "URL menu leaf \"$name\" ($url) wajib berupa rute yang diawali slash (/)."
                ];
            }
            if (isset($urlsByModule[$module][$url])) {
                $violations[] = [
                    "file" => $file,
                    "menu" => $name,
                    "reason" => "Duplikasi URL \"$url\" pada modul \"$module\" (sudah dipakai di: \"" . $urlsByModule[$module][$url] . "\")."
                ];
            }
            $urlsByModule[$module][$url] = $name;
        }

        // Validasi Children
        if (!empty($children) && is_array($children)) {
            $childOrders = [];
            foreach ($children as $child) {
                $totalMenuCount++;
                $cName = $child["name"] ?? "";
                $cUrl = $child["url"] ?? "";
                $cIcon = $child["icon"] ?? "";
                $cModule = $child["module"] ?? "";
                $cOrder = $child["order_index"] ?? null;

                if ($cModule !== $module) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "Child menu \"$cName\" memiliki module \"$cModule\", berbeda dengan parent \"$module\"."
                    ];
                }

                if (str_starts_with($cUrl, "#") || !str_starts_with($cUrl, "/")) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "URL sub-menu \"$cName\" tidak valid: \"$cUrl\". Wajib berupa path diawali slash (/)."
                    ];
                }

                if (isset($urlsByModule[$module][$cUrl])) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "Duplikasi URL \"$cUrl\" pada modul \"$module\"."
                    ];
                }
                $urlsByModule[$module][$cUrl] = $cName;

                if ($cOrder === null || !is_int($cOrder) || $cOrder < 1) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "Child menu \"$cName\" wajib memiliki order_index positif."
                    ];
                } elseif (in_array($cOrder, $childOrders)) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "Order index $cOrder pada sub-menu \"$cName\" bentrok/duplikat dalam section \"$name\"."
                    ];
                }
                $childOrders[] = $cOrder;

                if (!empty($cIcon) && !in_array($cIcon, $supportedIcons)) {
                    $violations[] = [
                        "file" => $file,
                        "menu" => "$name > $cName",
                        "reason" => "Ikon \"$cIcon\" tidak terdaftar di pemetaan Sidebar frontend."
                    ];
                }

                if ($cIcon === "FaList") {
                    $genericIconCount++;
                }
            }
        }
    }

    // Cek rasio ikon generic FaList tidak boleh mendominasi seeder
    if ($totalMenuCount > 10 && ($genericIconCount / $totalMenuCount) > 0.35) {
        $violations[] = [
            "file" => $file,
            "menu" => "Global Seeder",
            "reason" => "Penggunaan ikon generik FaList terlalu berlebihan (" . round(($genericIconCount / $totalMenuCount) * 100) . "%). Gunakan ikon semantik yang kontekstual (FaUsers, FaKey, FaDatabase, FaShieldAlt, dll)."
        ];
    }
}

if (!empty($violations)) {
    echo json_encode($violations);
}
' $TARGET_FILES)

if [ -n "$STATIC_CHECK_RESULT" ] && [ "$STATIC_CHECK_RESULT" != "[]" ]; then
    echo "❌ [Audit Menu Seeder Organization] REJECTED (Static Analysis)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    node -e "
      const data = JSON.parse(process.argv[1]);
      data.forEach((item, idx) => {
        console.log(\`[\${idx + 1}] File: \${item.file}\`);
        console.log(\`    Menu: \${item.menu}\`);
        console.log(\`    Alasan: \${item.reason}\`);
        console.log('--------------------------------------------------------------------------------');
      });
    " "$STATIC_CHECK_RESULT"
    echo "===================================================================================="
    echo "💡 Harap rapikan struktur seeder menu sesuai standar sebelum melakukan commit."
    exit 1
fi

if [ "$IS_MANUAL_RUN" = true ] && [ -z "$STAGED_DIFF" ]; then
    echo "✅ [Audit Menu Seeder Organization] PASSED (Static Analysis: Seluruh seeder menu terorganisasi rapi & ikon valid)."
    exit 0
fi

# ------------------------------------------------------------------------------
# 2. AI AUDIT (Jika ada staged diff dan tool AI tersedia)
# ------------------------------------------------------------------------------
if [ -n "$STAGED_DIFF" ] && { [ -x "$OPENCODE_BIN" ] || command -v agy &> /dev/null; }; then
    PROMPT_FILE=$(mktemp)

    cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Standar Organisasi Database Seeder Menu (Strict Reviewer).
Periksa Git Diff berikut HANYA terhadap 5 aturan pengorganisasian menu di bawah:

ATURAN BAKU PENGORGANISASIAN MENU SEEDER:
1. STRUKTUR HIRARKI & SECTION HEADER:
   - Menu dikelompokkan dalam kategori/section terorganisir (misal: MANAJEMEN PENGGUNA, ROLE & HAK AKSES, DATA REFERENSI, LOG & AUDIT).
   - DILARANG menumpuk menu secara flat di satu parent raksasa (maksimal 4-6 menu per section).
   - Nama section header WAJIB UPPERCASE dengan URL anchor diawali '#' (contoh: '#users_section', '#roles_section').
2. VALIDITAS URL & UNIK:
   - Setiap sub-menu / menu leaf WAJIB memiliki URL yang valid diawali '/' (contoh: '/admin/users'). DILARANG '#' pada leaf menu.
   - DILARANG duplikasi URL dalam modul yang sama.
3. STANDAR IKON SEMANTIK:
   - Ikon WAJIB kontekstual dan semantik (FaUsers untuk user, FaKey/FaShieldAlt untuk role/permission, FaDatabase/FaTags untuk referensi, FaHistory untuk audit log, FaCogs untuk pengaturan).
   - DILARANG membabi buta memakai ikon generik FaList / FaFileAlt untuk semua menu.
4. ORDER INDEX BERURUTAN:
   - Setiap menu dan child menu WAJIB memiliki order_index positif yang berurutan (1, 2, 3...) tanpa bentrok dalam parent yang sama.
5. KONSISTENSI MODUL:
   - Field 'module' pada child menu WAJIB sama dengan parent section.

Catatan:
- HANYA periksa baris baru (+) yaitu baris kode baru yang DITAMBAHKAN atau DIUBAH (diawali tanda `+`). JANGAN menolak baris konteks yang tidak diubah (tanpa `+`).

Git Diff:
EOF

    echo '```diff' >> "$PROMPT_FILE"
    echo "$STAGED_DIFF" >> "$PROMPT_FILE"
    echo '```' >> "$PROMPT_FILE"

    cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika seeder menu sudah terorganisasi rapi dan mematuhi seluruh aturan, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris yang melanggar)
  * Aturan yang Dilanggar: (sebutkan nomor aturan)
  * Alasan Penolakan: (penjelasan detail)
  * Solusi / Rekomendasi Perbaikan: (rekomendasi struktur menu yang rapi)
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

    if [ $AI_EXIT_CODE -eq 0 ]; then
        CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')
        if echo "$RESULT" | grep -qi "REJECTED"; then
            echo "❌ [Audit Menu Seeder Organization] REJECTED oleh AI!"
            echo "================================ DETAIL TEMUAN AUDIT ================================"
            echo "$CLEAN_RESULT"
            echo "===================================================================================="
            exit 1
        fi
    fi
fi

echo "✅ [Audit Menu Seeder Organization] PASSED (Seeder menu terorganisasi dengan rapi)."
exit 0
