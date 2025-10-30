# macOS App Builder for WordPress Apps

This directory contains scripts to build self-contained macOS `.app` bundles for WordPress applications that run via WordPress Playground CLI.

## Features

✅ Self-contained `.app` bundle with custom icon
✅ Dock-able application
✅ **Automatic requirement checking** - shows helpful error dialogs if Node.js or wp-now is missing
✅ **"Open Instructions" button** - launches installation guides directly from error dialogs
✅ Small status window with browser launch button
✅ All WordPress app files bundled inside
✅ Portable - share the `.app` with anyone

## Prerequisites

Users need to have WordPress Playground CLI installed:

```bash
npm install -g @wp-now/wp-now
```

## Building the App

```bash
cd build
./build-mac-app.sh [options]
```

### Options

- `--name "App Name"` - Application name (default: "Personal CRM")
- `--bundle-id com.example.app` - Bundle identifier (default: "com.personal-crm.app")
- `--port 8080` - Server port (default: 8080)
- `--icon path/to/icon.png` - App icon (.png or .icns)
- `--output ./dist` - Output directory (default: ./dist)

### Example

```bash
./build-mac-app.sh \
    --name "Personal CRM" \
    --bundle-id "com.personal-crm.app" \
    --port 8080 \
    --icon ../assets/icon.png \
    --output ../dist
```

## Output

The script creates a `.app` bundle in the output directory:
```
dist/
└── Personal CRM.app/
    └── Contents/
        ├── Info.plist
        ├── MacOS/
        │   └── launch.sh
        └── Resources/
            ├── app-icon.icns
            ├── blueprint.json
            └── wordpress-app/
                └── [all your WordPress app files]
```

## Using the App

1. Double-click `Personal CRM.app` to launch
2. **If requirements are missing**, the app shows a helpful dialog:
   - Checks for Node.js installation
   - Checks for npm availability
   - Checks for wp-now (WordPress Playground CLI)
   - Verifies Node.js version (requires 18+)
   - Offers "Open Instructions" button to launch installation guides
3. A dialog shows startup status
4. Browser opens automatically to `http://localhost:8080`
5. Another dialog keeps the server running
6. Click "Quit" to stop the server

### Example Error Dialogs

**Missing Node.js:**
```
Node.js is not installed.

Personal CRM requires Node.js to run WordPress Playground.

Install options:

• Download from nodejs.org (Recommended)
• Install via Homebrew: brew install node

[Cancel] [Open Instructions]
```

**Missing wp-now:**
```
WordPress Playground CLI (wp-now) is not installed.

Personal CRM uses wp-now to run WordPress locally.

Install by running this command in Terminal:

npm install -g @wp-now/wp-now

Then restart this app.

[Cancel] [Open Instructions]
```

## Distributing

To share the app:
1. Compress the `.app` bundle: `zip -r PersonalCRM.zip "Personal CRM.app"`
2. Share the zip file
3. Recipients just unzip and double-click

## Adapting for Other WordPress Apps

This build system is designed to be reusable:

1. Copy the `build/` directory to another WordPress project
2. Customize the icon and app name
3. Run `./build-mac-app.sh`
4. Get a new self-contained `.app`

The blueprint.json can be customized for different WordPress configurations.

## Technical Details

- **Bundle Structure**: Standard macOS `.app` bundle
- **Launch Script**: Bash script that spawns `wp-now`
- **Dialogs**: Native macOS dialogs via AppleScript
- **Browser**: Opens system default browser
- **Server**: WordPress Playground CLI (`wp-now`)

## Future Enhancements

This could be extracted into a separate tool:
- npm package: `npx wp-app-bundle`
- GitHub repo with install script
- Homebrew tap: `brew install wp-app-bundler`
