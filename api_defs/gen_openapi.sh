#!/bin/sh

PROJECT_ROOT=$(dirname $0)/..
cd "$PROJECT_ROOT"

# 既存生成ファイル削除
rm -f api_defs/openapi.generated.yml

echo "Generating OpenAPI spec from PHP attributes..."
echo "Working directory: $(pwd)"

# PHP attributesからOpenAPI生成
backend/vendor/bin/openapi \
    backend/src/trvis_backend/api/ \
    backend/src/trvis_backend/model_schemas/ \
    --output api_defs/openapi.generated.yml \
    --format yaml

# 検証
if [ -f api_defs/openapi.generated.yml ]; then
    echo "✓ OpenAPI spec generated: api_defs/openapi.generated.yml"

    # ファイルサイズ表示
    FILE_SIZE=$(wc -c < api_defs/openapi.generated.yml)
    echo "  Generated file size: $FILE_SIZE bytes"

    # Redoclyでlint（インストール済みの場合）
    if command -v redocly &> /dev/null; then
        echo "Running Redocly lint..."
        redocly lint api_defs/openapi.generated.yml
    else
        echo "Redocly not found. To install: npm install -g @redocly/cli"
    fi
else
    echo "✗ Failed to generate OpenAPI spec"
    exit 1
fi