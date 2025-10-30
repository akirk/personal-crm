# Building Personal CRM as a macOS App

This project includes a build system to create a distributable macOS `.app` bundle that runs Personal CRM via WordPress Playground.

## Quick Build

```bash
./build-personal-crm-app.sh
```

This creates: `dist/Personal CRM.app`

## What You Get

A self-contained macOS application that:
- ✅ Can be placed in the Dock
- ✅ Has a custom icon (if you provide one)
- ✅ Checks for requirements and shows helpful error dialogs
- ✅ Launches WordPress Playground with Personal CRM
- ✅ Opens your browser automatically
- ✅ Can be distributed to others (just zip and share)

## Prerequisites for End Users

The built app requires end users to have:
1. **Node.js** 18+ (from nodejs.org or `brew install node`)
2. **WordPress Playground CLI**: `npm install -g @wp-now/wp-now`

If these aren't installed, the app will show helpful dialogs with installation instructions.

## Adding a Custom Icon

### Quick Method:
1. Create or download a 1024x1024 PNG icon
2. Save it to `assets/app-icon.png`
3. Run `./build-personal-crm-app.sh`

### Using the Included Template:
```bash
cd build
# Edit app-icon.svg in any editor, then convert:
./generate-icon.sh
cd ..
./build-personal-crm-app.sh
```

See [build/ICON_GUIDE.md](build/ICON_GUIDE.md) for detailed icon creation instructions.

## Distributing the App

```bash
# Build the app
./build-personal-crm-app.sh

# Create a zip for distribution
cd dist
zip -r PersonalCRM.zip "Personal CRM.app"
```

Share `PersonalCRM.zip` with users. They just need to:
1. Unzip
2. Install Node.js and wp-now (if not already installed)
3. Double-click `Personal CRM.app`

## Customizing the Build

The generic build system is in `build/` and can be used for any WordPress app:

```bash
cd build
./build-mac-app.sh \
    --name "My App" \
    --bundle-id "com.example.my-app" \
    --port 8081 \
    --icon path/to/icon.png \
    --output ../dist
```

## Technical Details

- **Build System**: `build/` directory contains reusable templates
- **Launch Script**: Checks requirements, starts wp-now, manages browser
- **Blueprint**: `build/templates/blueprint.json.template` configures WordPress Playground
- **Documentation**: See `build/README.md` for the generic build system details

## Adapting for Other WordPress Apps

1. Copy the `build/` directory to another WordPress project
2. Create a project-specific build script (like `build-personal-crm-app.sh`)
3. Customize app name, bundle ID, and icon
4. Run the build

## Future: Extract to Separate Tool

This build system is designed to be extracted into a reusable tool:
- npm package: `npx wp-app-bundle`
- Homebrew tap: `brew install wp-app-bundler`
- GitHub repo with install script

See [build/WORDPRESS_PLAYGROUND_SETUP.md](build/WORDPRESS_PLAYGROUND_SETUP.md) for more details on distribution strategies.
