#!/bin/sh

cd $(dirname $0)

echo "Comparing OpenAPI specifications..."
echo "=================================="

# Check for existing bundled YAML or analyze original structure
echo "1. Analyzing original YAML structure..."
if [ -f openapi.bundle.yml ]; then
    BUNDLE_SIZE=$(wc -c < openapi.bundle.yml)
    echo "   ✓ Found existing bundled spec: openapi.bundle.yml ($BUNDLE_SIZE bytes)"
else
    echo "   → No existing bundle found, analyzing api_root.yaml"
    if [ -f api_root.yaml ]; then
        BUNDLE_SIZE=$(wc -c < api_root.yaml)
        echo "   ✓ Original root spec: api_root.yaml ($BUNDLE_SIZE bytes)"

        # Count references in YAML files
        YAML_PATHS=$(find paths -name "*.yaml" | wc -l)
        YAML_OBJECTS=$(find objects -name "*.yaml" | wc -l)
        echo "   → Path definitions: $YAML_PATHS files"
        echo "   → Object schemas: $YAML_OBJECTS files"
    else
        echo "   ✗ No original specs found"
        exit 1
    fi
fi

# Generate from new approach
echo "2. Generating OpenAPI from PHP attributes..."
./gen_openapi.sh > /dev/null 2>&1
if [ -f openapi.generated.yml ]; then
    GENERATED_SIZE=$(wc -c < openapi.generated.yml)
    echo "   ✓ Generated spec: openapi.generated.yml ($GENERATED_SIZE bytes)"
else
    echo "   ✗ Failed to generate spec from PHP attributes"
    exit 1
fi

# Compare file sizes
echo ""
echo "3. Size Comparison:"
echo "   Original bundled: $BUNDLE_SIZE bytes"
echo "   Generated from PHP: $GENERATED_SIZE bytes"
DIFF=$((GENERATED_SIZE - BUNDLE_SIZE))
if [ $DIFF -gt 0 ]; then
    echo "   Difference: +$DIFF bytes (Generated is larger)"
else
    echo "   Difference: $DIFF bytes (Generated is smaller)"
fi

# Check endpoints and schemas
echo ""
echo "4. Endpoint & Schema Analysis:"
GENERATED_PATHS=$(grep -E "^\s*(/|'/)" openapi.generated.yml | grep -v "^\s*'[0-9]" | wc -l)
GENERATED_SCHEMAS=$(grep -c "^\s\+[A-Z][A-Za-z]*:$" openapi.generated.yml)

if [ -f openapi.bundle.yml ]; then
    ORIGINAL_PATHS=$(grep "^\s*/" openapi.bundle.yml | wc -l)
    ORIGINAL_SCHEMAS=$(grep -c "^\s\+[A-Z][A-Za-z]*:$" openapi.bundle.yml)
    echo "   Original endpoints: $ORIGINAL_PATHS"
    echo "   Generated endpoints: $GENERATED_PATHS"
    echo "   Original schemas: $ORIGINAL_SCHEMAS"
    echo "   Generated schemas: $GENERATED_SCHEMAS"
else
    # Count expected from YAML files
    EXPECTED_PATHS=$(grep -r "operationId:" paths/ | wc -l)
    EXPECTED_SCHEMAS=$(ls objects/*.yaml | wc -l)
    echo "   Expected endpoints: $EXPECTED_PATHS (from path files)"
    echo "   Generated endpoints: $GENERATED_PATHS"
    echo "   Expected schemas: $EXPECTED_SCHEMAS (from object files)"
    echo "   Generated schemas: $GENERATED_SCHEMAS"
fi

# List what's implemented so far
echo ""
echo "6. Currently Implemented Endpoints:"
grep -E "^\s*(/|'/)" openapi.generated.yml | grep -v "^\s*'[0-9]" | sed 's/^\s*/   /'

echo ""
echo "Comparison complete!"
echo ""
echo "To see detailed differences, run:"
echo "   diff -u openapi.bundle.yml openapi.generated.yml"

# Optional: Use openapi-diff if available
if command -v openapi-diff >/dev/null 2>&1; then
    echo ""
    echo "7. Detailed API Comparison (openapi-diff):"
    openapi-diff openapi.bundle.yml openapi.generated.yml
else
    echo ""
    echo "Note: Install openapi-diff for detailed API comparison:"
    echo "   npm install -g openapi-diff"
fi