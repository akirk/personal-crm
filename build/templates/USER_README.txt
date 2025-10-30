PERSONAL CRM - macOS Application
================================

Thank you for downloading Personal CRM!

FIRST-TIME SETUP
===============

1. INSTALL REQUIREMENTS (if not already installed)

   This app needs Node.js and WordPress Playground to run.

   a) Install Node.js:
      Download from: https://nodejs.org/
      Or via Homebrew: brew install node

   b) Install WordPress Playground CLI:
      Open Terminal and run:
      npm install -g @wp-now/wp-now

2. OPEN THE APP (IMPORTANT - READ THIS!)

   macOS will block the app on first run because it's not signed.
   This is normal and safe. Here's how to open it:

   METHOD 1 (Easiest):
   - RIGHT-CLICK on "Personal CRM.app"
   - Select "Open" from the menu
   - Click "Open" in the confirmation dialog

   METHOD 2 (If right-click doesn't work):
   - Try to open the app normally (it will be blocked)
   - Go to: System Settings → Privacy & Security
   - Scroll down and click "Open Anyway"
   - Confirm in the dialog

   You only need to do this ONCE. After that, you can open the app normally!

3. USE THE APP

   - The app will show a startup dialog
   - Your browser will open automatically
   - A small window stays open while the app runs
   - Click "Quit" when you're done

TROUBLESHOOTING
==============

"Cannot open because it is from an unidentified developer"
→ Use the right-click method above (this is expected)

"Node.js is not installed"
→ Install Node.js from https://nodejs.org/

"WordPress Playground CLI is not installed"
→ Run in Terminal: npm install -g @wp-now/wp-now

App won't start or shows errors
→ Check the log file at: /tmp/wp-now-8080.log

Port 8080 already in use
→ Another app is using that port. Close other apps and try again.

UNINSTALL
=========

1. Drag "Personal CRM.app" to Trash
2. (Optional) Remove WordPress Playground:
   npm uninstall -g @wp-now/wp-now

PRIVACY & SECURITY
=================

This app runs WordPress locally on your computer. No data is sent to external
servers. All your information stays on your machine.

The app uses:
- Node.js (JavaScript runtime)
- WordPress Playground (local WordPress environment)
- SQLite database (stored locally)

SUPPORT
=======

For issues or questions, visit:
https://github.com/akirk/personal-crm

VERSION INFO
============

App: Personal CRM
Version: 1.0.0
Platform: macOS
