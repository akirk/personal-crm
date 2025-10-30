# macOS Distribution & Code Signing Guide

## The Problem: Gatekeeper

When users download your `.app` bundle, macOS Gatekeeper will block it from running with this error:

```
"Personal CRM.app" cannot be opened because it is from an unidentified developer.
```

This happens because:
1. The app is **not signed** with an Apple Developer ID
2. The app is **not notarized** by Apple
3. Downloaded files get a "quarantine" flag that triggers Gatekeeper

## Solutions (Ranked by Effort)

### Option 1: User Workaround (Zero Cost, Manual Steps)

**For end users who download the app:**

#### Method A: Right-Click to Open
1. Right-click (or Control+click) on `Personal CRM.app`
2. Select "Open" from menu
3. Click "Open" in the confirmation dialog
4. App will run and be remembered as safe

#### Method B: System Settings (macOS Ventura+)
1. Try to open the app (gets blocked)
2. Open System Settings → Privacy & Security
3. Scroll down to see "Personal CRM was blocked"
4. Click "Open Anyway"
5. Confirm in the dialog

#### Method C: Remove Quarantine Flag (Terminal)
```bash
xattr -d com.apple.quarantine "/path/to/Personal CRM.app"
```

**Documentation to include with your app:**
```
INSTALLATION INSTRUCTIONS

macOS will block this app on first run because it's not signed.
This is normal and safe.

To open:
1. Right-click the app
2. Select "Open"
3. Click "Open" in the dialog

You only need to do this once!
```

### Option 2: Remove Quarantine Before Distribution (Low Effort)

**As the developer**, before zipping the app:

```bash
# Remove quarantine attribute locally
xattr -cr "dist/Personal CRM.app"

# Then zip
cd dist
zip -r PersonalCRM.zip "Personal CRM.app"
```

**However**: This doesn't help because the quarantine flag gets **re-added** when users download the zip file from the internet.

### Option 3: Distribute Without Quarantine Flag (Medium Effort)

**Methods that avoid the quarantine flag:**

#### A. Direct Transfer
- **AirDrop**: No quarantine flag
- **USB drive**: No quarantine flag
- **Network share** (SMB/AFP): No quarantine flag
- **Internal company network**: No quarantine flag

#### B. Host on Your Own Server with Special Headers
Configure your web server to not set quarantine attributes:

```apache
# Apache
<FilesMatch "\.app\.zip$">
    Header set X-Download-Options "noopen"
</FilesMatch>
```

**Note**: This doesn't always work; macOS still applies quarantine in many cases.

### Option 4: Code Signing (Professional, $99/year)

**Get an Apple Developer ID:**
1. Join Apple Developer Program ($99/year)
2. Create Developer ID Application certificate
3. Sign your app bundle

**Sign the app:**
```bash
# Sign the app bundle
codesign --force --deep --sign "Developer ID Application: Your Name (TEAMID)" \
    "dist/Personal CRM.app"

# Verify signature
codesign --verify --verbose "dist/Personal CRM.app"
spctl --assess --verbose "dist/Personal CRM.app"
```

**Add to build script:**

I can add an optional `--sign` parameter to the build script:

```bash
./build-mac-app.sh \
    --name "Personal CRM" \
    --icon icon.png \
    --sign "Developer ID Application: Your Name" \
    --output dist
```

**Benefits:**
- ✅ App opens without warnings
- ✅ Users trust it more
- ✅ More professional
- ⚠️ Still shows warning if not notarized (macOS 10.15+)

### Option 5: Notarization (Professional, $99/year + effort)

**Full professional distribution:**

1. **Code sign** the app (see Option 4)
2. **Zip the signed app**
3. **Submit to Apple for notarization**:
   ```bash
   # Create app-specific password at appleid.apple.com

   # Submit for notarization
   xcrun notarytool submit PersonalCRM.zip \
       --apple-id "your@email.com" \
       --team-id "TEAMID" \
       --password "app-specific-password" \
       --wait

   # Staple the notarization ticket
   xcrun stapler staple "dist/Personal CRM.app"
   ```
4. **Verify**:
   ```bash
   spctl -a -vv "dist/Personal CRM.app"
   ```

**Notarization Requirements:**
- Hardened runtime enabled
- Secure timestamp
- No deprecated APIs
- Apple scans for malware

**Benefits:**
- ✅ No warnings at all
- ✅ Professional grade
- ✅ Required for wide distribution on macOS 10.15+
- ✅ Automatically trusted by Gatekeeper

## Recommendation by Use Case

### For Personal/Internal Use
→ **Option 1** (User workaround)
- Include simple instructions with the app
- One-time right-click to open

### For Small Team/Beta Testing
→ **Option 3** (AirDrop/Network share)
- No web downloads = no quarantine flag
- Share via Slack, Teams, or internal network

### For Public Distribution (Free)
→ **Option 1** with good documentation
- README with screenshots showing the right-click process
- Video tutorial if needed
- Accept that users need one manual step

### For Professional Product
→ **Option 5** (Full signing + notarization)
- Worth the $99/year for professional image
- Zero friction for users
- Required for any serious distribution

## Automated Code Signing in Build Script

I can enhance the build script to support optional code signing:

```bash
./build-mac-app.sh \
    --name "Personal CRM" \
    --sign "Developer ID Application: Your Name (TEAMID)" \
    --notarize \
    --apple-id "your@email.com" \
    --team-id "TEAMID" \
    --output dist
```

This would:
1. Build the app
2. Sign it with your Developer ID
3. Optionally submit for notarization
4. Staple the ticket
5. Ready to distribute!

## What About the Shell Script Inside?

**Additional consideration**: The `.app` bundle contains a shell script (`launch.sh`).

**For notarization**, you may need to:
1. Sign the shell script too: `codesign --sign "Developer ID" launch.sh`
2. Enable hardened runtime
3. Add entitlements for network access

**Or**: Bundle the Node.js runtime and wp-now inside the app to avoid external dependencies, then sign everything together.

## Testing Code Signing

```bash
# Check if app is signed
codesign -dv "dist/Personal CRM.app"

# Check signature validity
codesign --verify --verbose "dist/Personal CRM.app"

# Check Gatekeeper will allow it
spctl --assess --type execute --verbose "dist/Personal CRM.app"

# Test notarization status
stapler validate "dist/Personal CRM.app"
```

## Windows Equivalent

**Windows has similar issues:**
- SmartScreen filter blocks unsigned executables
- Need code signing certificate (~$100-500/year)
- User workaround: "More info" → "Run anyway"

The same distribution strategies apply to Windows.

## Summary

| Method | Cost | User Friction | Best For |
|--------|------|---------------|----------|
| User workaround | $0 | Medium | Personal/Internal |
| AirDrop/Network | $0 | Low | Small teams |
| Code signing only | $99/yr | Low-Medium | Semi-professional |
| Sign + Notarize | $99/yr + effort | None | Professional |

For a **reusable tool** that others will adapt: I recommend building in **optional code signing support** so developers with Apple Developer accounts can sign their apps, while still supporting unsigned distribution for testing.
