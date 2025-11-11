#!/bin/bash

##
# Voxel Toolkit Plugin Packaging Script
#
# Creates a distributable ZIP file of the plugin, excluding development files
# Usage: ./package.sh
##

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}==================================${NC}"
echo -e "${GREEN}Voxel Toolkit Packaging Script${NC}"
echo -e "${GREEN}==================================${NC}"
echo ""

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Extract version from plugin file
PLUGIN_FILE="voxel-toolkit.php"

if [ ! -f "$PLUGIN_FILE" ]; then
    echo -e "${RED}Error: $PLUGIN_FILE not found!${NC}"
    exit 1
fi

VERSION=$(grep -i "Version:" "$PLUGIN_FILE" | head -1 | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from $PLUGIN_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}Plugin Version:${NC} $VERSION"
echo ""

# Define output directory and filename
DIST_DIR="dist"
OUTPUT_FILE="voxel-toolkit-${VERSION}.zip"
OUTPUT_PATH="${DIST_DIR}/${OUTPUT_FILE}"

# Create dist directory if it doesn't exist
if [ ! -d "$DIST_DIR" ]; then
    echo -e "${YELLOW}Creating dist directory...${NC}"
    mkdir -p "$DIST_DIR"
fi

# Check if zip already exists
if [ -f "$OUTPUT_PATH" ]; then
    echo -e "${YELLOW}Warning: $OUTPUT_FILE already exists.${NC}"
    read -p "Do you want to overwrite it? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Packaging cancelled.${NC}"
        exit 1
    fi
    rm "$OUTPUT_PATH"
fi

echo -e "${YELLOW}Creating package...${NC}"
echo ""

# Get the parent directory name
PARENT_DIR=$(dirname "$SCRIPT_DIR")
PLUGIN_DIR_NAME="voxel-toolkit"

# Create temporary directory structure
TEMP_DIR=$(mktemp -d)
TEMP_PLUGIN_DIR="${TEMP_DIR}/${PLUGIN_DIR_NAME}"

# Copy files to temp directory with proper structure
echo -e "${YELLOW}Preparing files...${NC}"
mkdir -p "$TEMP_PLUGIN_DIR"

# Copy all files except excluded ones to temp directory
rsync -a \
    --exclude=".*" \
    --exclude="dist/" \
    --exclude="*.zip" \
    --exclude="node_modules/" \
    --exclude="package.sh" \
    --exclude="*.md" \
    "$SCRIPT_DIR/" "$TEMP_PLUGIN_DIR/"

# Create the ZIP file from temp directory
cd "$TEMP_DIR"
zip -r "$SCRIPT_DIR/$OUTPUT_PATH" "$PLUGIN_DIR_NAME" > /dev/null 2>&1
ZIP_RESULT=$?

# Clean up temp directory
cd "$SCRIPT_DIR"
rm -rf "$TEMP_DIR"

if [ $ZIP_RESULT -eq 0 ]; then
    FILE_SIZE=$(du -h "$OUTPUT_PATH" | cut -f1)
    echo -e "${GREEN}✓ Package created successfully!${NC}"
    echo ""
    echo -e "${GREEN}Output:${NC} $OUTPUT_PATH"
    echo -e "${GREEN}Size:${NC} $FILE_SIZE"
    echo ""
    echo -e "${YELLOW}Contents:${NC}"
    unzip -l "$OUTPUT_PATH" | tail -n +4 | head -n 20
    echo ""
else
    echo -e "${RED}Error: Failed to create package${NC}"
    exit 1
fi

echo -e "${GREEN}==================================${NC}"
echo -e "${GREEN}Packaging Complete!${NC}"
echo -e "${GREEN}==================================${NC}"
