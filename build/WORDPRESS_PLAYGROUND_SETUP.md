# WordPress Playground Requirements & Setup

## How It Works

This macOS app launcher uses **WordPress Playground CLI** (`wp-now`) to run WordPress applications locally without needing:
- XAMPP, MAMP, or traditional web servers
- MySQL/MariaDB database installation
- PHP installation
- Manual WordPress setup

## User Requirements

### 1. Install Node.js (if not already installed)
WordPress Playground CLI requires Node.js:

**Option A: Using Homebrew**
```bash
brew install node
```

**Option B: Download from nodejs.org**
Download from https://nodejs.org/ (LTS version recommended)

### 2. Install WordPress Playground CLI
Once Node.js is installed, users need to install `wp-now` globally:

```bash
npm install -g @wp-now/wp-now
```

That's it! These are the ONLY two requirements.

## What Happens When the App Launches

1. **User double-clicks `PersonalCRM.app`**
2. The app checks if `wp-now` is installed
   - If not found: Shows an error dialog with installation instructions
   - If found: Continues...
3. The app runs: `wp-now start --path=[bundled files] --port=8080`
4. WordPress Playground:
   - Creates a temporary WordPress installation
   - Uses SQLite (no MySQL needed!)
   - Installs and activates the bundled plugin/app
   - Starts a local web server
5. App shows status dialog and opens browser to `http://localhost:8080`
6. User works with the WordPress app
7. When done, user clicks "Quit" and the server stops

## Distribution Approaches

### Option 1: Require Pre-installation (Current Approach)
**Pros:**
- Smaller app bundle
- Always uses latest wp-now version
- Simple to maintain

**Cons:**
- Users must install Node.js and wp-now separately
- Slight friction for non-technical users

**Distribution:**
```
1. Share PersonalCRM.app.zip
2. Include setup instructions:
   - Install Node.js
   - Run: npm install -g @wp-now/wp-now
   - Launch the app
```

### Option 2: Bundle Node.js + wp-now (Advanced)
You could bundle Node.js and wp-now inside the `.app` bundle for a completely self-contained experience.

**Pros:**
- Zero external dependencies
- One-click launch for users
- Professional feel

**Cons:**
- Much larger app bundle (~50-100 MB vs. ~5 MB)
- Need to maintain bundled Node.js/wp-now versions
- More complex build process

**Implementation:**
```
PersonalCRM.app/
  Contents/
    MacOS/
      node/          # Bundled Node.js
      node_modules/  # Bundled wp-now
      launch.sh      # Uses bundled node
```

### Option 3: Installer Package
Create a `.pkg` installer that:
1. Checks for Node.js (installs if missing)
2. Installs wp-now globally
3. Installs the PersonalCRM.app

**Pros:**
- Professional installation experience
- Handles all dependencies
- Can include shortcuts, dock icons, etc.

**Cons:**
- More complex to build
- Requires code signing for macOS Gatekeeper

## Recommendation for Your Use Case

For a **reusable tool** that works with any WordPress app, I recommend:

### **Option 1 (Current)** for development and power users
- Simple, lightweight
- Easy to adapt for different WordPress apps
- Good for internal tools or tech-savvy users

### **Option 2 (Bundled)** for end-user distribution
- Create a second build script: `build-mac-app-standalone.sh`
- Downloads and bundles Node.js + wp-now
- Results in self-contained app
- Better for non-technical users

## Handling Different WordPress Playground Versions

The blueprint.json can specify WordPress Playground configuration:
- WordPress version
- PHP version
- Plugins to install
- Theme to activate
- Database setup

Example for different apps:
```json
{
  "preferredVersions": {
    "php": "8.2",
    "wp": "6.4"
  },
  "plugins": [
    "your-custom-plugin"
  ]
}
```

## Making This a Reusable Tool

When you extract this to a separate project (npm package, Homebrew, etc.), you could offer both approaches:

```bash
# Lightweight (requires wp-now)
wp-app-bundle --name "My App" --path . --icon icon.png

# Standalone (bundles everything)
wp-app-bundle --name "My App" --path . --icon icon.png --standalone
```

## Windows Equivalent

The Windows version would work similarly:
- Require Node.js + wp-now (or bundle them)
- Create a small C# or PowerShell GUI
- Launch wp-now subprocess
- Open browser to localhost
- Show status window with quit button

Same concept, different platform tools!
