#!/bin/bash

set -e

# Default values
APP_NAME="Personal CRM"
BUNDLE_ID="com.personal-crm.app"
PORT=8080
ICON_PATH=""
OUTPUT_DIR="./dist"
SIGN_IDENTITY=""

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --name)
            APP_NAME="$2"
            shift 2
            ;;
        --bundle-id)
            BUNDLE_ID="$2"
            shift 2
            ;;
        --port)
            PORT="$2"
            shift 2
            ;;
        --icon)
            ICON_PATH="$2"
            shift 2
            ;;
        --output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --sign)
            SIGN_IDENTITY="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--name 'App Name'] [--bundle-id com.example.app] [--port 8080] [--icon path/to/icon.png] [--output ./dist] [--sign 'Developer ID']"
            exit 1
            ;;
    esac
done

echo "Building macOS app bundle..."
echo "  App Name: $APP_NAME"
echo "  Bundle ID: $BUNDLE_ID"
echo "  Port: $PORT"

# Create output directory and convert to absolute path
mkdir -p "$OUTPUT_DIR"
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"

# App bundle path
APP_BUNDLE="$OUTPUT_DIR/${APP_NAME}.app"
CONTENTS_DIR="$APP_BUNDLE/Contents"
MACOS_DIR="$CONTENTS_DIR/MacOS"
RESOURCES_DIR="$CONTENTS_DIR/Resources"
WP_APP_DIR="$RESOURCES_DIR/wordpress-app"

# Remove existing bundle if it exists
if [ -d "$APP_BUNDLE" ]; then
    echo "Removing existing app bundle..."
    rm -rf "$APP_BUNDLE"
fi

# Create bundle structure
echo "Creating app bundle structure..."
mkdir -p "$MACOS_DIR"
mkdir -p "$RESOURCES_DIR"
mkdir -p "$WP_APP_DIR"

# Copy all project files to wordpress-app (excluding build, dist, .git, etc.)
echo "Copying project files..."
pushd "$PROJECT_ROOT" > /dev/null
find . -type f \
    -not -path './build/*' \
    -not -path './dist/*' \
    -not -path './.git/*' \
    -not -path './node_modules/*' \
    -not -path './tests/*' \
    -not -name '*.md' \
    -not -name '.gitignore' | while read -r file; do
    dir=$(dirname "$file")
    mkdir -p "$WP_APP_DIR/$dir"
    cp "$file" "$WP_APP_DIR/$file"
done
popd > /dev/null

# Process Info.plist template
echo "Creating Info.plist..."
sed -e "s/{{APP_NAME}}/$APP_NAME/g" \
    -e "s/{{BUNDLE_ID}}/$BUNDLE_ID/g" \
    "$SCRIPT_DIR/templates/Info.plist.template" > "$CONTENTS_DIR/Info.plist"

# Process launch.sh template
echo "Creating launch script..."
sed -e "s/{{APP_NAME}}/$APP_NAME/g" \
    -e "s/{{PORT}}/$PORT/g" \
    "$SCRIPT_DIR/templates/launch.sh.template" > "$MACOS_DIR/launch.sh"

chmod +x "$MACOS_DIR/launch.sh"

# Copy blueprint.json
echo "Creating blueprint.json..."
cp "$SCRIPT_DIR/templates/blueprint.json.template" "$RESOURCES_DIR/blueprint.json"

# Handle icon
if [ -n "$ICON_PATH" ] && [ -f "$ICON_PATH" ]; then
    echo "Processing icon..."
    ICON_EXT="${ICON_PATH##*.}"

    if [ "$ICON_EXT" = "icns" ]; then
        # Already in icns format
        cp "$ICON_PATH" "$RESOURCES_DIR/app-icon.icns"
    elif [ "$ICON_EXT" = "png" ]; then
        # Convert PNG to ICNS (requires iconutil on macOS)
        if command -v sips &> /dev/null && command -v iconutil &> /dev/null; then
            ICONSET_DIR="/tmp/AppIcon.iconset"
            mkdir -p "$ICONSET_DIR"

            # Generate different sizes
            sips -z 16 16     "$ICON_PATH" --out "$ICONSET_DIR/icon_16x16.png" &>/dev/null
            sips -z 32 32     "$ICON_PATH" --out "$ICONSET_DIR/icon_16x16@2x.png" &>/dev/null
            sips -z 32 32     "$ICON_PATH" --out "$ICONSET_DIR/icon_32x32.png" &>/dev/null
            sips -z 64 64     "$ICON_PATH" --out "$ICONSET_DIR/icon_32x32@2x.png" &>/dev/null
            sips -z 128 128   "$ICON_PATH" --out "$ICONSET_DIR/icon_128x128.png" &>/dev/null
            sips -z 256 256   "$ICON_PATH" --out "$ICONSET_DIR/icon_128x128@2x.png" &>/dev/null
            sips -z 256 256   "$ICON_PATH" --out "$ICONSET_DIR/icon_256x256.png" &>/dev/null
            sips -z 512 512   "$ICON_PATH" --out "$ICONSET_DIR/icon_256x256@2x.png" &>/dev/null
            sips -z 512 512   "$ICON_PATH" --out "$ICONSET_DIR/icon_512x512.png" &>/dev/null
            sips -z 1024 1024 "$ICON_PATH" --out "$ICONSET_DIR/icon_512x512@2x.png" &>/dev/null

            iconutil -c icns "$ICONSET_DIR" -o "$RESOURCES_DIR/app-icon.icns"
            rm -rf "$ICONSET_DIR"
        else
            echo "Warning: sips/iconutil not available, icon conversion skipped"
        fi
    else
        echo "Warning: Unsupported icon format: $ICON_EXT (use .png or .icns)"
    fi
else
    echo "No icon provided, creating placeholder..."
    # Create a simple placeholder icon (empty icns)
    touch "$RESOURCES_DIR/app-icon.icns"
fi

# Copy user README if it exists
if [ -f "$SCRIPT_DIR/templates/USER_README.txt" ]; then
    cp "$SCRIPT_DIR/templates/USER_README.txt" "$OUTPUT_DIR/README.txt"
fi

# Code signing (if requested)
if [ -n "$SIGN_IDENTITY" ]; then
    echo "Code signing app bundle..."
    echo "  Identity: $SIGN_IDENTITY"

    # Sign the launch script first
    codesign --force --sign "$SIGN_IDENTITY" "$MACOS_DIR/launch.sh" 2>/dev/null || true

    # Sign the entire app bundle
    if codesign --force --deep --sign "$SIGN_IDENTITY" "$APP_BUNDLE"; then
        echo "✅ App bundle signed successfully"

        # Verify signature
        if codesign --verify --verbose "$APP_BUNDLE" 2>&1 | grep -q "valid on disk"; then
            echo "✅ Signature verified"
        else
            echo "⚠️  Warning: Signature verification had issues"
        fi

        # Check Gatekeeper status
        if spctl --assess --type execute --verbose "$APP_BUNDLE" 2>&1 | grep -q "accepted"; then
            echo "✅ Gatekeeper will accept this app"
        else
            echo "ℹ️  Note: App is signed but not notarized"
            echo "   Users won't see Gatekeeper warnings, but notarization is recommended"
        fi
    else
        echo "❌ Code signing failed"
        echo "   Make sure the identity '$SIGN_IDENTITY' is valid"
        echo "   List available identities: security find-identity -v -p codesigning"
        exit 1
    fi
    echo ""
fi

echo ""
echo "✅ App bundle created successfully!"
echo "📦 Location: $APP_BUNDLE"
echo ""

if [ -z "$SIGN_IDENTITY" ]; then
    echo "⚠️  App is NOT signed"
    echo ""
    echo "Users will see a Gatekeeper warning on first run."
    echo "They need to right-click → Open to bypass it."
    echo ""
    echo "To sign the app, rebuild with:"
    echo "  --sign 'Developer ID Application: Your Name (TEAMID)'"
    echo ""
    echo "See build/MACOS_DISTRIBUTION.md for details."
    echo ""
fi

echo "To use:"
echo "  1. Double-click the app to launch"
if [ -z "$SIGN_IDENTITY" ]; then
    echo "  2. Right-click → Open on first launch (Gatekeeper workaround)"
    echo "  3. Drag to Applications folder or Dock"
else
    echo "  2. Drag to Applications folder or Dock"
fi
echo ""
echo "Prerequisites for users:"
echo "  - Node.js 18+ (from nodejs.org)"
echo "  - WordPress Playground CLI: npm install -g @wp-now/wp-now"
echo ""
echo "See README.txt in output directory for user instructions."
echo ""
