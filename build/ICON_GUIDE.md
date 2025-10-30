# App Icon Guide

## Quick Start

The build script accepts icons in two formats:
- **PNG** (1024x1024 recommended) - will be auto-converted to .icns
- **ICNS** (macOS icon format) - used directly

## Option 1: Use the Included SVG Template

We've included `app-icon.svg` - a simple purple gradient icon with a person silhouette.

### Convert to PNG:

**Method A: Using Preview (macOS built-in)**
1. Open `app-icon.svg` in Preview
2. File → Export
3. Format: PNG
4. Save to `../assets/app-icon.png`
5. Resize to 1024x1024 if needed

**Method B: Using online converter**
1. Visit https://cloudconvert.com/svg-to-png
2. Upload `app-icon.svg`
3. Set size to 1024x1024
4. Download and save to `../assets/app-icon.png`

**Method C: Using command line** (requires homebrew)
```bash
# Install librsvg
brew install librsvg

# Convert
./generate-icon.sh
```

## Option 2: Create Your Own Icon

### Requirements:
- **Size**: 1024x1024 pixels minimum (for best quality)
- **Format**: PNG or ICNS
- **Shape**: Square with rounded corners looks best on macOS
- **Content**: Should be visible at small sizes (16x16)

### Design Tips:
1. Use a bold, simple design
2. Avoid fine details (won't show at small sizes)
3. Consider a background color or gradient
4. Leave some padding around the edges
5. Test at 128x128 to see how it looks in Dock

### Tools:

**Free Online Tools:**
- [Canva](https://www.canva.com/) - Use "App Icon" template
- [Figma](https://www.figma.com/) - Free design tool
- [IconKitchen](https://icon.kitchen/) - App icon generator

**macOS Apps:**
- Sketch
- Affinity Designer
- GIMP (free)
- Preview (for simple editing)

**AI-Generated:**
- DALL-E, Midjourney, or Stable Diffusion
- Prompt: "app icon for personal CRM, minimal, purple gradient, 1024x1024"

## Option 3: Use an Icon Font or Emoji

You can create a simple colored square with an emoji or icon:

```bash
# Example using ImageMagick (install: brew install imagemagick)
convert -size 1024x1024 xc:'#4F46E5' \
    -gravity center \
    -pointsize 600 \
    -font 'Apple-Color-Emoji' \
    -annotate +0+0 '👤' \
    ../assets/app-icon.png
```

## Option 4: Download an Icon

**Free Icon Sources:**
- [Icons8](https://icons8.com/) - Free icons (attribution required)
- [Flaticon](https://www.flaticon.com/) - Free icons
- [Noun Project](https://thenounproject.com/) - Simple icons

Search for: "contact", "people", "CRM", "user", "address book"

## Using Your Icon

Once you have an icon at `assets/app-icon.png`:

```bash
# Build with icon
./build-personal-crm-app.sh
```

Or manually:
```bash
cd build
./build-mac-app.sh \
    --name "Personal CRM" \
    --icon ../assets/app-icon.png \
    --output ../dist
```

## Converting PNG to ICNS (Advanced)

The build script automatically converts PNG to ICNS, but if you want to do it manually:

```bash
# Create iconset
mkdir AppIcon.iconset

# Generate different sizes (requires sips - macOS built-in)
sips -z 16 16     app-icon.png --out AppIcon.iconset/icon_16x16.png
sips -z 32 32     app-icon.png --out AppIcon.iconset/icon_16x16@2x.png
sips -z 32 32     app-icon.png --out AppIcon.iconset/icon_32x32.png
sips -z 64 64     app-icon.png --out AppIcon.iconset/icon_32x32@2x.png
sips -z 128 128   app-icon.png --out AppIcon.iconset/icon_128x128.png
sips -z 256 256   app-icon.png --out AppIcon.iconset/icon_128x128@2x.png
sips -z 256 256   app-icon.png --out AppIcon.iconset/icon_256x256.png
sips -z 512 512   app-icon.png --out AppIcon.iconset/icon_256x256@2x.png
sips -z 512 512   app-icon.png --out AppIcon.iconset/icon_512x512.png
sips -z 1024 1024 app-icon.png --out AppIcon.iconset/icon_512x512@2x.png

# Convert to icns
iconutil -c icns AppIcon.iconset -o app-icon.icns
```

## Testing Your Icon

After building:
1. Open Finder
2. Navigate to `dist/Personal CRM.app`
3. View as icons (Cmd+1) to see large icon
4. Drag to Dock to see how it looks small
5. Right-click Dock icon → Options → Keep in Dock

## No Icon? No Problem!

The build script works without an icon - it just creates a blank placeholder. The app will still function perfectly, just without a custom icon.
