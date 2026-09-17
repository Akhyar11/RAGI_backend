#!/bin/bash
# ==============================================================================
# AUDIT MANUAL RUNNER — integrated_sistem_backend
# Menjalankan seluruh modul auditor AI Opencode Muse secara manual
# ==============================================================================

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_DIR" || exit 1

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
export OPENCODE_MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

TARGET="$1"

if [ -n "$TARGET" ]; then
    export DIFF_TARGET="$TARGET"
    echo "🔍 [Audit Manual] Memeriksa perubahan pada target: $TARGET"
elif [ -n "$(git diff --cached --name-only)" ]; then
    unset DIFF_TARGET
    echo "🔍 [Audit Manual] Memeriksa staged changes (git diff --cached)..."
else
    export DIFF_TARGET="HEAD~1..HEAD"
    echo "🔍 [Audit Manual] Tidak ada staged changes. Memeriksa commit terkini (HEAD~1..HEAD)..."
fi

echo "🚀 [AGY Agent Audit Runner] Menjalankan auditor dengan model: $OPENCODE_MODEL"
echo "------------------------------------------------------------------------"

FAILED=0
AUDIT_DIR="$REPO_DIR/.githooks/audits"

for script in "$AUDIT_DIR"/*.sh; do
    if [ -x "$script" ]; then
        echo ""
        "$script"
        EXIT_CODE=$?
        if [ $EXIT_CODE -ne 0 ]; then
            FAILED=1
        fi
    fi
done

echo ""
echo "========================================================================"
if [ $FAILED -ne 0 ]; then
    echo "❌ [Audit Manual] SEBAGIAN AUDIT GAGAL / REJECTED!"
    echo "💡 Harap tinjau catatan kegagalan di atas."
    exit 1
else
    echo "🎉 [Audit Manual] SEMUA AUDIT PASSED! (Divalidasi oleh AI Opencode Muse)"
    exit 0
fi
