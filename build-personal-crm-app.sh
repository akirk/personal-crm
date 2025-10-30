#!/bin/bash

# Build script specifically for Personal CRM app
# This is an example of how to use the generic build system for a specific app

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BUILD_DIR="$SCRIPT_DIR/build"
ICON_PATH="$SCRIPT_DIR/assets/app-icon.png"

echo "🏗️  Building Personal CRM macOS App..."
echo ""

# Check if icon exists
if [ ! -f "$ICON_PATH" ]; then
    echo "⚠️  No icon found at $ICON_PATH"
    echo ""
    echo "To create an icon:"
    echo "  1. cd build && ./generate-icon.sh"
    echo "  OR"
    echo "  2. Place a 1024x1024 PNG at assets/app-icon.png"
    echo ""
    echo "Building without icon for now..."
    ICON_ARG=""
else
    echo "✅ Using icon: $ICON_PATH"
    ICON_ARG="--icon $ICON_PATH"
fi

# Run the build
cd "$BUILD_DIR"
./build-mac-app.sh \
    --name "Personal CRM" \
    --bundle-id "com.alex-kirk.personal-crm" \
    --port 8080 \
    $ICON_ARG \
    --output "$SCRIPT_DIR/dist"

echo ""
echo "✅ Build complete!"
echo ""
echo "📦 App location: $SCRIPT_DIR/dist/Personal CRM.app"
echo ""
echo "To test:"
echo "  open '$SCRIPT_DIR/dist/Personal CRM.app'"
echo ""
echo "To distribute:"
echo "  cd dist && zip -r PersonalCRM.zip 'Personal CRM.app'"
