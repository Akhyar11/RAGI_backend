#!/bin/bash
# ==============================================================================
# AUDIT 01 (BE): Syntax, Hygiene & Structure Standard (Deterministic Fast Check)
# ==============================================================================
# Memeriksa:
# 1. Sintaks PHP (php -l) pada seluruh file staged
# 2. Hygiene Kode: larangan statement debug (dd, dump, var_dump, print_r, ray, die, exit)
# 3. Config Hygiene: larangan pemanggilan env()/getenv() langsung di app/ (wajib config())
# 4. Validasi Struktur & Ikon MenuSeeder (terdaftar di frontend Sidebar.tsx)
# ==============================================================================

echo "🔍 [Audit 1/3: Syntax, Hygiene & Structure] Memeriksa sintaks PHP, kebersihan kode, dan struktur..."

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_PHP=$(git diff "$DIFF_TARGET" --name-only --diff-filter=ACM -- "*.php")
    STAGED_PHP_DIFF=$(git diff "$DIFF_TARGET" -- "*.php")
    STAGED_DIFF=$(git diff "$DIFF_TARGET")
else
    STAGED_PHP=$(git diff --cached --name-only --diff-filter=ACM -- "*.php")
    STAGED_PHP_DIFF=$(git diff --cached -- "*.php")
    STAGED_DIFF=$(git diff --cached)
fi

if [ -z "$STAGED_PHP" ] && [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit 1/3] Tidak ada perubahan kode yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. PHP SYNTAX CHECK (php -l)
# ------------------------------------------------------------------------------
if command -v php &> /dev/null && [ -n "$STAGED_PHP" ]; then
    FAILED_SYNTAX=0
    while IFS= read -r file; do
        [ -f "$file" ] || continue
        OUTPUT=$(php -l "$file" 2>&1)
        if [ $? -ne 0 ]; then
            echo "❌ [Audit Syntax] Kesalahan sintaks PHP ditemukan di $file:"
            echo "$OUTPUT"
            FAILED_SYNTAX=1
        fi
    done <<< "$STAGED_PHP"

    if [ $FAILED_SYNTAX -ne 0 ]; then
        echo "💡 Perbaiki kesalahan sintaks PHP di atas sebelum commit."
        exit 1
    fi
fi

# ------------------------------------------------------------------------------
# 2. CODE HYGIENE CHECK (No debug functions in added lines of PHP files)
# ------------------------------------------------------------------------------
DEBUG_MATCHES=$(echo "$STAGED_PHP_DIFF" | grep -E '^[+]\s*(dd|dump|var_dump|print_r|ray)\s*\(' | grep -v '^[+]\s*//' | grep -v '^[+]\s*\*')
DIE_MATCHES=$(echo "$STAGED_PHP_DIFF" | grep -E '^[+]\s*(die|exit)\s*(\(|;)' | grep -v '^[+]\s*//' | grep -v '^[+]\s*\*')

if [ -n "$DEBUG_MATCHES" ] || [ -n "$DIE_MATCHES" ]; then
    echo "❌ [Audit Hygiene] Ditemukan fungsi debug/terminasi mentah pada baris baru (+):"
    [ -n "$DEBUG_MATCHES" ] && echo "$DEBUG_MATCHES"
    [ -n "$DIE_MATCHES" ] && echo "$DIE_MATCHES"
    echo "💡 Hapus seluruh panggilan dd(), dump(), var_dump(), print_r(), ray(), die, atau exit sebelum commit."
    exit 1
fi

# ------------------------------------------------------------------------------
# 3. CONFIG HYGIENE (No env() in app/)
# ------------------------------------------------------------------------------
if [ -n "$DIFF_TARGET" ]; then
    STAGED_APP_DIFF=$(git diff "$DIFF_TARGET" -- "app/")
else
    STAGED_APP_DIFF=$(git diff --cached -- "app/")
fi

if [ -n "$STAGED_APP_DIFF" ]; then
    ENV_MATCHES=$(echo "$STAGED_APP_DIFF" | grep -E '^[+]\s*[^/].*\b(env|getenv)\s*\(' | grep -v '^[+]\s*//' | grep -v '^[+]\s*\*')
    if [ -n "$ENV_MATCHES" ]; then
        echo "❌ [Audit Config Hygiene] Ditemukan penggunaan env() atau getenv() langsung di direktori app/:"
        echo "$ENV_MATCHES"
        echo "💡 Gunakan config('key.name') alih-alih env() langsung di dalam app/."
        exit 1
    fi
fi

# ------------------------------------------------------------------------------
# 4. MENU SEEDER STATIC VALIDATION (If MenuSeeder modified)
# ------------------------------------------------------------------------------
MENU_SEEDER_DIFF=$(echo "$STAGED_DIFF" | grep -E 'diff --git a/database/seeders/.+Menu.+Seeder\.php' || true)
if [ -n "$MENU_SEEDER_DIFF" ]; then
    STATIC_CHECK=$(php -r '
    $supportedIcons = [];
    $sidebarCandidates = [
        getcwd() . "/../RAGIFrontend/components/layout/Sidebar.tsx",
        getcwd() . "/../RAGI/RAGIFrontend/components/layout/Sidebar.tsx",
    ];
    foreach ($sidebarCandidates as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (preg_match_all("/Fa[A-Za-z0-9]+/", $content, $matches)) {
                $supportedIcons = array_flip($matches[0]);
            }
            break;
        }
    }
    // Static fallback
    if (empty($supportedIcons)) {
        $supportedIcons = array_flip([
            "FaHome","FaUsersCog","FaUserShield","FaKey","FaShieldAlt","FaUserClock",
            "FaHistory","FaFileAlt","FaGraduationCap","FaClipboardCheck","FaMoneyBillWave",
            "FaFileInvoiceDollar","FaHandHoldingUsd","FaCashRegister","FaBook","FaBookOpen",
            "FaCoins","FaTags","FaUsers","FaUserTie","FaBuilding","FaIdBadge","FaBriefcase",
            "FaFolderOpen","FaCalendarCheck","FaCalendarAlt","FaCalendarTimes","FaChartBar",
            "FaChartPie","FaChartLine","FaTasks","FaExchangeAlt","FaFileSignature","FaUniversity",
            "FaLaptopCode","FaUserGraduate","FaChalkboardTeacher","FaAward","FaCertificate",
            "FaReceipt","FaCogs","FaCog","FaDatabase","FaSlidersH"
        ]);
    }

    $stagedFiles = explode("\n", trim(shell_exec("git diff --cached --name-only -- \"database/seeders/**/*Menu*Seeder*.php\" 2>/dev/null") ?? ""));
    $violations = [];

    foreach ($stagedFiles as $f) {
        if (!file_exists($f)) continue;
        $content = file_get_contents($f);
        if (preg_match_all("/[\x27\"]icon[\x27\"]\s*=>\s*[\x27\"]([^\x27\"]+)[\x27\"]/", $content, $iconMatches)) {
            foreach ($iconMatches[1] as $icon) {
                if ($icon !== "" && !isset($supportedIcons[$icon])) {
                    $violations[] = "Ikon non-standar atau tidak terdaftar di Sidebar.tsx: " . $icon;
                }
            }
        }
    }

    if (!empty($violations)) {
        echo implode("\n", array_unique($violations));
        exit(1);
    }
    exit(0);
    ')
    if [ $? -ne 0 ]; then
        echo "❌ [Audit Menu Seeder] Pelanggaran validasi MenuSeeder:"
        echo "$STATIC_CHECK"
        echo "💡 Pastikan ikon terdaftar di Sidebar.tsx."
        exit 1
    fi
fi

echo "✅ [Audit 1/3: Syntax, Hygiene & Structure] PASSED."
exit 0
