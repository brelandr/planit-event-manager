#!/bin/bash
# Script to create WordPress.org submission zip file
# Excludes development files and system files

PLUGIN_NAME="the-wordpress-event-calendar"
ZIP_NAME="${PLUGIN_NAME}.zip"

echo "Creating WordPress.org submission zip: ${ZIP_NAME}"

# Remove old zip if it exists
if [ -f "${ZIP_NAME}" ]; then
    rm "${ZIP_NAME}"
    echo "Removed old zip file"
fi

# Create zip excluding development and system files
zip -r "${ZIP_NAME}" . \
    -x "*.DS_Store" \
    -x ".cursorrules" \
    -x ".git/*" \
    -x ".gitignore" \
    -x "*.log" \
    -x "node_modules/*" \
    -x "vendor/*" \
    -x ".vscode/*" \
    -x ".idea/*" \
    -x "*.swp" \
    -x "*.swo" \
    -x "*~" \
    -x "*.tmp" \
    -x "*.temp" \
    -x ".cache/*" \
    -x "dist/*" \
    -x "build/*" \
    -x "create-plugin-zip.sh" \
    -x "*.md" \
    -x "FEATURES-COMPARISON.md" \
    -x "PRO-FEATURES.md" \
    -x "QUICK-START.md" \
    -x "TROUBLESHOOTING.md" \
    -x "README.md"

if [ $? -eq 0 ]; then
    echo "✓ Zip file created successfully: ${ZIP_NAME}"
    echo "File size: $(ls -lh ${ZIP_NAME} | awk '{print $5}')"
    echo ""
    echo "Verifying excluded files are not in zip..."
    if unzip -l "${ZIP_NAME}" | grep -qE "(\.cursorrules|\.DS_Store|\.git)"; then
        echo "⚠ WARNING: Some excluded files may still be in the zip!"
    else
        echo "✓ Confirmed: .cursorrules, .DS_Store, and .git are excluded"
    fi
else
    echo "✗ Error creating zip file"
    exit 1
fi

