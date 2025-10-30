#!/bin/bash

# Script to generate app icon from SVG
# This creates a 1024x1024 PNG that can be used by the build script

SVG_FILE="app-icon.svg"
PNG_FILE="../assets/app-icon.png"

echo "Generating app icon..."

# Check for available tools
if command -v rsvg-convert &> /dev/null; then
    # Use rsvg-convert (from librsvg, available via Homebrew: brew install librsvg)
    rsvg-convert -w 1024 -h 1024 "$SVG_FILE" -o "$PNG_FILE"
    echo "✅ Icon created using rsvg-convert: $PNG_FILE"
elif command -v inkscape &> /dev/null; then
    # Use Inkscape (available via Homebrew: brew install inkscape)
    inkscape "$SVG_FILE" --export-filename="$PNG_FILE" --export-width=1024 --export-height=1024
    echo "✅ Icon created using Inkscape: $PNG_FILE"
elif command -v convert &> /dev/null; then
    # Use ImageMagick (available via Homebrew: brew install imagemagick)
    convert -background none -size 1024x1024 "$SVG_FILE" "$PNG_FILE"
    echo "✅ Icon created using ImageMagick: $PNG_FILE"
else
    echo "❌ No SVG converter found."
    echo ""
    echo "Please install one of:"
    echo "  • librsvg:     brew install librsvg"
    echo "  • Inkscape:    brew install inkscape"
    echo "  • ImageMagick: brew install imagemagick"
    echo ""
    echo "Or convert the SVG manually:"
    echo "  1. Open app-icon.svg in Preview or any image editor"
    echo "  2. Export as PNG at 1024x1024"
    echo "  3. Save to ../assets/app-icon.png"
    echo ""
    echo "Alternative: Use an online converter like https://cloudconvert.com/svg-to-png"
    exit 1
fi

echo ""
echo "Now you can build the app with the icon:"
echo "  ./build-mac-app.sh --name 'Personal CRM' --icon $PNG_FILE --output ../dist"
