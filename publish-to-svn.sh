#!/bin/bash
# Script to publish PlanIt Event Manager to WordPress.org SVN
# Usage: ./publish-to-svn.sh

set -e

PLUGIN_NAME="planit-event-manager"
PLUGIN_VERSION="1.0.11"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_NAME}/"
LOCAL_SVN_DIR="../planit-event-manager-svn"
PLUGIN_DIR="$(pwd)"

echo "=========================================="
echo "Publishing ${PLUGIN_NAME} v${PLUGIN_VERSION} to WordPress.org SVN"
echo "=========================================="
echo ""

# Check if SVN is installed
if ! command -v svn &> /dev/null; then
    echo "❌ ERROR: SVN is not installed."
    echo "Please install SVN first:"
    echo "  macOS: brew install svn"
    echo "  Linux: sudo apt-get install subversion (or equivalent)"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "planit-event-manager.php" ]; then
    echo "❌ ERROR: planit-event-manager.php not found."
    echo "Please run this script from the plugin root directory."
    exit 1
fi

echo "Step 1: Setting up SVN directory..."
if [ -d "${LOCAL_SVN_DIR}/.svn" ]; then
    echo "  SVN directory already exists. Updating..."
    cd "${LOCAL_SVN_DIR}"
    svn update
else
    echo "  Checking out SVN repository..."
    cd "$(dirname ${LOCAL_SVN_DIR})"
    svn checkout "${SVN_URL}" "${PLUGIN_NAME}-svn"
    cd "${PLUGIN_NAME}-svn"
fi

echo ""
echo "Step 2: Preparing plugin files for trunk..."
cd "${PLUGIN_DIR}"

# Create a temporary directory for clean files
TEMP_DIR=$(mktemp -d)
echo "  Creating clean copy in: ${TEMP_DIR}"

# Copy files excluding development files
rsync -av --progress \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='.cursorrules' \
    --exclude='.cursor' \
    --exclude='.vscode' \
    --exclude='.idea' \
    --exclude='*.log' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*~' \
    --exclude='*.tmp' \
    --exclude='*.temp' \
    --exclude='.cache' \
    --exclude='dist' \
    --exclude='build' \
    --exclude='assets' \
    --exclude='create-plugin-zip.sh' \
    --exclude='tests' \
    --exclude='phpcs.xml' \
    --exclude='phpcs.xml.dist' \
    --exclude='*.md' \
    --exclude='FEATURES-COMPARISON.md' \
    --exclude='PRO-FEATURES.md' \
    --exclude='QUICK-START.md' \
    --exclude='TROUBLESHOOTING.md' \
    --exclude='README.md' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='PLUGIN-IDENTIFIER.txt' \
    --exclude='release-data.json' \
    --exclude='*.zip' \
    --exclude='*.gz' \
    --exclude='*.tar' \
    --exclude='*.tar.gz' \
    --exclude='*.tgz' \
    --exclude='*.rar' \
    --exclude='*.7z' \
    --exclude='*.bz2' \
    --exclude='*.xz' \
    --exclude='publish-to-svn.sh' \
    --exclude='EXTERNAL-API-AUDIT.md' \
    --exclude='SECURITY-AUDIT.md' \
    --exclude='CODING-STANDARDS-AUDIT.md' \
    --exclude='NONCE-SECURITY-AUDIT.md' \
    --exclude='AJAX-SECURITY-AUDIT.md' \
    --exclude='*.md' \
    . "${TEMP_DIR}/${PLUGIN_NAME}/"

echo ""
echo "Step 3: Copying files to SVN trunk..."
cd "${LOCAL_SVN_DIR}"
svn rm --force trunk/* 2>/dev/null || true
cp -r "${TEMP_DIR}/${PLUGIN_NAME}"/* trunk/
rm -rf "${TEMP_DIR}"

echo ""
echo "Step 4: Adding new files to SVN..."
cd trunk
svn add --force .

echo ""
echo "Step 5: Creating version tag..."
cd "${LOCAL_SVN_DIR}"
if [ -d "tags/${PLUGIN_VERSION}" ]; then
    echo "  Tag ${PLUGIN_VERSION} already exists. Removing..."
    svn rm "tags/${PLUGIN_VERSION}"
fi
svn cp trunk "tags/${PLUGIN_VERSION}"

echo ""
echo "=========================================="
echo "Ready to commit!"
echo "=========================================="
echo ""
echo "Review the changes:"
echo "  cd ${LOCAL_SVN_DIR}"
echo "  svn status"
echo ""
echo "To commit to WordPress.org:"
echo "  svn commit -m 'Publishing version ${PLUGIN_VERSION}'"
echo ""
echo "Note: You'll need to enter your WordPress.org SVN credentials."
echo ""
read -p "Do you want to commit now? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Committing to WordPress.org SVN..."
    svn commit -m "Publishing version ${PLUGIN_VERSION}"
    echo ""
    echo "✅ Successfully published ${PLUGIN_NAME} v${PLUGIN_VERSION}!"
    echo ""
    echo "Your plugin should be live at:"
    echo "  https://wordpress.org/plugins/${PLUGIN_NAME}/"
else
    echo ""
    echo "Changes are ready but not committed."
    echo "Review and commit manually when ready."
fi

