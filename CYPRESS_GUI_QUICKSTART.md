# Cypress GUI Quick Start for WSL2

## ✅ What's Been Set Up

1. ✅ Cypress binary installed (v15.7.0)
2. ✅ DevContainer configured for X11 forwarding
3. ✅ Helper script created: `./scripts/cypress-gui.sh`
4. ✅ Dependencies installed in Dockerfile

## 🚀 Next Steps to Launch Cypress GUI

### Step 1: Install VcXsrv on Windows (One-time)

Download: https://sourceforge.net/projects/vcxsrv/

### Step 2: Start VcXsrv

1. Launch **XLaunch** on Windows
2. **Display settings**: Multiple windows, Display: `0`
3. **Client startup**: Start no client
4. **Extra settings**: ✅ **Check "Disable access control"** ← CRITICAL!
5. Save configuration

**VcXsrv must be running whenever you use Cypress GUI**

### Step 3: Rebuild DevContainer (One-time)

The environment variables need to take effect:

1. Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on Mac)
2. Type: "Dev Containers: Rebuild Container"
3. Wait for rebuild to complete

### Step 4: Launch Cypress GUI

```bash
# Option 1: Use helper script (recommended)
./scripts/cypress-gui.sh

# Option 2: Direct command
npx cypress open
```

### Step 5: Select Test Mode

In Cypress GUI:
1. Click **"E2E Testing"**
2. Choose **"Chrome"** browser
3. Click **"Start E2E Testing in Chrome"**
4. Select a test file from the list

## 🧪 Quick Test

Test X11 connection before launching Cypress:

```bash
# Should show display info (not an error)
xdpyinfo -display $DISPLAY

# Verify environment
echo $DISPLAY  # Should show: host.docker.internal:0
```

## 🐛 Troubleshooting

### "cannot connect to X11 display"

**Check:**
1. VcXsrv is running on Windows
2. "Disable access control" is checked in VcXsrv
3. Windows Firewall allows VcXsrv (if prompted, allow on Private networks)

**Fix:**
```bash
# Restart VcXsrv, then rebuild devcontainer
```

### "DISPLAY not set" or shows old value

You need to rebuild the devcontainer for new environment variables.

### VcXsrv crashes or closes

Restart XLaunch and ensure "Disable access control" is checked.

### Cypress GUI is slow/laggy

This is normal for X11 forwarding. Consider:
- Use headless mode for regular testing: `npx cypress run`
- Use GUI only when debugging specific tests

## 📚 Full Documentation

See `docs/cypress-gui-setup.md` for:
- Alternative methods (WSLg, x11-bridge)
- Detailed troubleshooting
- Configuration explanations

## 🎯 Current Configuration

**DevContainer Settings** (`.devcontainer/devcontainer.json`):
```json
"containerEnv": {
    "DISPLAY": "${localEnv:WSL_HOST_IP:-host.docker.internal}:0",
    "LIBGL_ALWAYS_INDIRECT": "1",
    "QT_X11_NO_MITSHM": "1"
}
```

**Docker Compose** (`.devcontainer/docker-compose.yml`):
```yaml
extra_hosts:
  - "host.docker.internal:host-gateway"
```

## Alternative: Headless Mode (No X Server Needed)

If you just need screenshots/videos for debugging:

```bash
# Run all tests headless
npx cypress run

# Run specific test
npx cypress run --spec "cypress/e2e/tutorial-simple-test.cy.js"

# Results saved to:
# - data_tests/cypress/screenshots/<timestamp>/
# - data_tests/cypress/videos/<timestamp>/
```

---

**Ready to go!** Install VcXsrv → Start it → Rebuild container → Run `./scripts/cypress-gui.sh`
