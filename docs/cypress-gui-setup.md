# Cypress GUI Setup for WSL2 DevContainer

This guide explains how to run the Cypress interactive GUI from within the WSL2-based devcontainer.

## Prerequisites

The Cypress binary is now installed and the devcontainer is configured with X11 support.

## ✅ Option 1: VcXsrv (Recommended)

This is the most reliable method for WSL2 + Docker + Cypress GUI.

### 1. Install VcXsrv on Windows

Download and install from: https://sourceforge.net/projects/vcxsrv/

### 2. Configure and Start VcXsrv

Launch **XLaunch** and configure:

1. **Display settings**: Select "Multiple windows", Display number: `0`
2. **Client startup**: Select "Start no client"
3. **Extra settings**:
   - ✅ **Check "Disable access control"** (CRITICAL)
   - ✅ Check "Clipboard" (optional, useful)
   - ✅ Check "Primary Selection" (optional)
4. **Save configuration** for future use

**Important:** VcXsrv must be running whenever you want to use Cypress GUI.

### 3. Configure Windows Firewall (If Needed)

If connection fails, you may need to allow VcXsrv through Windows Firewall:

1. Open Windows Defender Firewall → Allow an app
2. Find VcXsrv or XLaunch
3. Allow on **Private networks** (at minimum)

### 4. Launch Cypress GUI

From within the devcontainer terminal:

```bash
# Easy way - use the helper script
./scripts/cypress-gui.sh

# Or directly
npx cypress open
```

### 5. Troubleshooting VcXsrv

**Issue: "cannot connect to X11 display"**

Check:
```bash
# Verify DISPLAY variable is set
echo $DISPLAY
# Should show: host.docker.internal:0 or similar

# Test X11 connection
xdpyinfo -display $DISPLAY
```

**Fix steps:**
1. Ensure VcXsrv is running on Windows
2. Verify "Disable access control" is checked in VcXsrv settings
3. Restart VcXsrv
4. Rebuild devcontainer: `Ctrl+Shift+P` → "Rebuild Container"

**Issue: "Error: connect ECONNREFUSED"**

The display server is not reachable. Check:
1. VcXsrv is running
2. Windows firewall allows connections
3. `host.docker.internal` resolves correctly:
   ```bash
   getent hosts host.docker.internal
   ```

---

## Option 2: WSLg (Native WSL2 GUI Support)

If you have Windows 11 or recent Windows 10 with WSLg support:

### 1. Check if WSLg is available

From WSL2 (not devcontainer):
```bash
echo $DISPLAY
# Should show something like :0 or :1
```

### 2. Update devcontainer configuration

Modify `.devcontainer/devcontainer.json`:

```json
"containerEnv": {
    "DISPLAY": ":0",
    "WAYLAND_DISPLAY": "wayland-0",
    "XDG_RUNTIME_DIR": "/tmp",
    "PULSE_SERVER": "/mnt/wslg/PulseServer"
}
```

And update `.devcontainer/docker-compose.yml`:

```yaml
webserver:
  volumes:
    - /tmp/.X11-unix:/tmp/.X11-unix:rw
    - /mnt/wslg:/mnt/wslg:rw
  environment:
    - DISPLAY=:0
```

### 3. Rebuild and test

```bash
# Rebuild devcontainer
# Then test
npx cypress open
```

---

## Option 3: x11-bridge Container (Alternative)

Uses the `jare/x11-bridge` container for web-based X11 access.

### 1. Ensure x11-bridge is running

Check docker-compose.yml includes:
```yaml
x11-bridge:
  image: jare/x11-bridge
  volumes:
    - "/tmp/.X11-unix:/tmp/.X11-unix:rw"
  ports:
    - "10000:10000"
  restart: always
  environment:
    MODE: tcp
    XPRA_HTML: "yes"
    DISPLAY: :14
    XPRA_PASSWORD: MUST_BE_SOMETHING
```

### 2. Update webserver DISPLAY

```json
"containerEnv": {
    "DISPLAY": "x11-bridge:14"
}
```

### 3. Access via browser

1. Start Cypress: `npx cypress open`
2. Open browser: `http://localhost:10000`
3. Enter password: `MUST_BE_SOMETHING`

**Note:** This method has higher latency but works without Windows X server.

---

## Current Configuration

Your devcontainer is currently configured for **Option 1 (VcXsrv)** with:

- `DISPLAY=host.docker.internal:0` (uses Windows host X server)
- `LIBGL_ALWAYS_INDIRECT=1` (compatibility mode)
- `QT_X11_NO_MITSHM=1` (fixes shared memory issues)
- `extra_hosts: host.docker.internal:host-gateway` (Docker 20.10+ host resolution)

## Quick Start Commands

```bash
# Install Cypress binary (already done)
npx cypress install

# Launch Cypress GUI (VcXsrv must be running)
./scripts/cypress-gui.sh

# Or directly
npx cypress open

# Run headless tests (no GUI needed)
npx cypress run

# Run specific test file
npx cypress run --spec "cypress/e2e/tutorial-simple-test.cy.js"
```

## Environment Variables Explained

| Variable | Value | Purpose |
|----------|-------|---------|
| `DISPLAY` | `host.docker.internal:0` | X11 display location |
| `LIBGL_ALWAYS_INDIRECT` | `1` | Use indirect rendering (compatibility) |
| `QT_X11_NO_MITSHM` | `1` | Disable MIT-SHM extension (fixes crashes) |

## Testing the Setup

```bash
# 1. Test X11 connection
xdpyinfo -display $DISPLAY

# 2. Test with simple X11 app (if available)
xclock  # Should open a clock window on Windows

# 3. Verify Cypress binary
npx cypress --version

# 4. Open Cypress GUI
npx cypress open
```

## Common Issues

### Cypress opens but no browser shown

This is expected on first run. Click "E2E Testing" → Choose browser → "Start E2E Testing"

### "Browser binary not found"

Cypress uses the Chrome/Chromium installed in the Docker image. Check:
```bash
chrome --version
```

### Performance is slow

- VcXsrv performance depends on network/graphics
- Consider using headless mode for CI: `npx cypress run`
- GUI is mainly for debugging specific tests

### Window decorations missing

This is normal - VcXsrv in "multiple windows" mode integrates with Windows desktop.

## Alternative: Headless Mode with Screenshots

If GUI doesn't work, you can still debug with:

```bash
# Run tests headless with video/screenshots
npx cypress run

# Results saved to:
# - data_tests/cypress/screenshots/
# - data_tests/cypress/videos/
```

## Resources

- [Cypress on Docker](https://www.cypress.io/blog/2019/05/02/run-cypress-with-a-single-docker-command/)
- [WSL2 GUI Apps](https://learn.microsoft.com/en-us/windows/wsl/tutorials/gui-apps)
- [VcXsrv Documentation](https://sourceforge.net/projects/vcxsrv/)
- [GitHub Discussion](https://github.com/cypress-io/cypress-documentation/issues/2956#issuecomment-930527836)
