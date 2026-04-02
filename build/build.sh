#!/usr/bin/env bash

# This script generates the module zip locally just like the GitHub Action does.

MODULE_NAME="zulipreminder"

# Extract version from the main module class file
VERSION=$(grep -oE "\->version\s*=\s*'[^']+" ../core/modules/modZulipReminder.class.php | awk -F"'" '{print $2}')
if [ -z "$VERSION" ]; then
    echo "Warning: Could not extract version. Defaulting to 1.0.0"
    VERSION="1.0.0"
fi

PACKAGE_NAME="module_${MODULE_NAME}-${VERSION}"
echo "Building $PACKAGE_NAME.zip..."

# Go to the module root directory
cd ..

# Create a temporary directory for zipping
mkdir -p "build_tmp/${MODULE_NAME}"

# Copy everything into the module subfolder, excluding dev files
rsync -av --progress . "build_tmp/${MODULE_NAME}/" \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.gitignore' \
    --exclude='test' \
    --exclude='build_tmp' \
    --exclude='build' \
    --exclude='*.zip' \
    --exclude='*.back'

# Create the ZIP file
cd build_tmp
zip -r "../${PACKAGE_NAME}.zip" "${MODULE_NAME}"
cd ..

# Cleanup the temporary directory
rm -rf build_tmp

echo "Done! The zip file has been created at: ${PACKAGE_NAME}.zip"
