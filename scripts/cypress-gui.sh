#!/bin/bash
# Script to launch Cypress GUI from WSL2 devcontainer
# Requires VcXsrv running on Windows host

set -e

echo "🚀 Starting Cypress GUI..."
echo "📍 DISPLAY: $DISPLAY"

# Check if DISPLAY is set
if [ -z "$DISPLAY" ]; then
    echo "❌ ERROR: DISPLAY environment variable is not set"
    echo "Make sure VcXsrv is running on your Windows host"
    exit 1
fi

# Test X11 connection
if ! xdpyinfo -display "$DISPLAY" >/dev/null 2>&1; then
    echo "⚠️  WARNING: Cannot connect to X11 display $DISPLAY"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. Make sure VcXsrv is running on Windows"
    echo "2. Check 'Disable access control' is enabled in VcXsrv"
    echo "3. Verify Windows firewall allows VcXsrv connections"
    echo "4. Try rebuilding the devcontainer"
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "✅ X11 connection OK"
echo "🌐 Base URL: http://localhost (internal container port 80)"
echo ""
echo "Opening Cypress..."

# Set environment variable to indicate we're inside container
export CYPRESS_CONTAINER=true

# Launch Cypress GUI
npx cypress open
