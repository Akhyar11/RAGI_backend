#!/bin/bash

# Script untuk mengonfigurasi dan mengaktifkan Git Hooks di repository ini

echo "🔧 [AGY Git Hooks Setup] Mengonfigurasi git core.hooksPath ke folder .githooks..."

# Dapatkan direktori utama repository git
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null)

if [ -z "$REPO_ROOT" ]; then
    echo "❌ Error: Direktori saat ini bukan merupakan repository Git."
    exit 1
fi

cd "$REPO_ROOT" || exit 1

# Set core.hooksPath ke .githooks
git config core.hooksPath .githooks

# Berikan izin eksekusi (executable permission) ke semua file hook dan script audit
chmod +x .githooks/pre-commit 2>/dev/null
chmod +x .githooks/setup.sh 2>/dev/null

if [ -d ".githooks/audits" ]; then
    chmod +x .githooks/audits/*.sh 2>/dev/null
fi

echo "✅ [AGY Git Hooks Setup] Berhasil! Git hooks telah aktif di .githooks."
echo "💡 Pre-commit auditor kini otomatis berjalan setiap kali Anda melakukan 'git commit'."
