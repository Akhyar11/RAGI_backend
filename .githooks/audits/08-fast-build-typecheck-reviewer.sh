#!/bin/bash

echo "🤖 [Audit 8/8: Fast Build & TypeCheck Standard] Memeriksa integritas TypeScript & sintaks..."

# Cek apakah ada file ts/tsx yang di-stage
STAGED_FILES=$(git diff --cached --name-only -- "*.ts" "*.tsx")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit Fast Build & TypeCheck] Tidak ada file TypeScript (.ts/.tsx) yang di-stage. Skip."
    exit 0
fi

echo "🔍 Menjalankan Fast TypeScript Verification (tsc --noEmit)..."

TSC_OUTPUT=$(npx tsc --noEmit 2>&1)
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    echo ""
    echo "❌ [Audit Fast Build & TypeCheck] TYPECHECK GAGAL!"
    echo "-----------------------------------------------------"
    echo "$TSC_OUTPUT" | head -n 25
    echo "-----------------------------------------------------"
    echo "💡 Ditemukan kesalahan tipe data/sintaks TypeScript. Harap perbaiki sebelum commit."
    exit 1
else
    echo "✅ [Audit Fast Build & TypeCheck] PASSED (0 kesalahan tipe data/sintaks)."
    exit 0
fi
