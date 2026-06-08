#!/bin/bash
# Script to update readme.txt in WordPress.org SVN with GitHub URL for blueprint

set -e

PLANIT_DEV_ROOT="/Users/randy/wordpress-plugins/WordpressDev/planit"

SVN_URL="https://plugins.svn.wordpress.org/planit-event-manager/"
LOCAL_SVN_DIR="${PLANIT_DEV_ROOT}/planit-event-manager-svn"
PLUGIN_DIR="${PLANIT_DEV_ROOT}/planit-event-manager"
PLUGIN_VERSION="1.0.0"

echo "=========================================="
echo "Updating readme.txt in WordPress.org SVN"
echo "=========================================="
echo ""

# Check if SVN is installed
if ! command -v svn &> /dev/null; then
    echo "❌ ERROR: SVN is not installed."
    echo "Please install SVN first: brew install svn"
    exit 1
fi

# Step 1: Check out or update SVN
echo "Step 1: Setting up SVN directory..."
if [ -d "${LOCAL_SVN_DIR}/.svn" ]; then
    echo "  SVN directory exists. Updating..."
    cd "${LOCAL_SVN_DIR}"
    svn update
else
    echo "  Checking out SVN repository..."
    mkdir -p "$(dirname "${LOCAL_SVN_DIR}")"
    cd "$(dirname "${LOCAL_SVN_DIR}")"
    svn checkout "${SVN_URL}" "$(basename "${LOCAL_SVN_DIR}")"
    cd "${LOCAL_SVN_DIR}"
fi

echo ""
echo "Step 2: Copying updated readme.txt..."
cd "${PLUGIN_DIR}"
cp readme.txt "${LOCAL_SVN_DIR}/trunk/readme.txt"
cp readme.txt "${LOCAL_SVN_DIR}/tags/${PLUGIN_VERSION}/readme.txt"

echo "  ✓ Copied to trunk/readme.txt"
echo "  ✓ Copied to tags/${PLUGIN_VERSION}/readme.txt"

echo ""
echo "Step 3: Checking SVN status..."
cd "${LOCAL_SVN_DIR}"
svn status trunk/readme.txt tags/${PLUGIN_VERSION}/readme.txt

echo ""
echo "Step 4: Committing to WordPress.org..."
svn commit -m "Update readme.txt: Use GitHub raw URL for blueprint to fix Playground fetch error" trunk/readme.txt tags/${PLUGIN_VERSION}/readme.txt

echo ""
echo "✅ Successfully updated readme.txt in WordPress.org SVN!"
echo ""
echo "The preview link now uses:"
echo "  https://raw.githubusercontent.com/brelandr/planit-event-manager/main/blueprint.json"
echo ""
echo "Wait 1-2 hours for WordPress.org cache to refresh, then test the preview link!"

