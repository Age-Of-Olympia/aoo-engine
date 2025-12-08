#!/bin/bash

###############################################################################
# Tutorial System - Production Readiness Test Runner
#
# This script runs the complete tutorial system validation:
# 1. Resets test database to ensure clean state
# 2. Runs complete first-time tutorial test
# 3. Resets database again (for second test)
# 4. Runs resume & persistence test
# 5. Reports results
#
# Usage:
#   /var/www/html/scripts/testing/run_tutorial_production_tests.sh
#
# Exit codes:
#   0 - All tests passed (production ready!)
#   1 - One or more tests failed (not production ready)
###############################################################################

set -e  # Exit on error

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Timestamp for this test run
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_DIR="/var/www/html/data_tests/tutorial_production_tests/${TIMESTAMP}"
mkdir -p "${LOG_DIR}"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Tutorial System - Production Readiness Test Suite        ║${NC}"
echo -e "${BLUE}║  Timestamp: ${TIMESTAMP}                            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Track overall status
OVERALL_STATUS=0

# Function to log step
log_step() {
    echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $1"
}

# Function to log success
log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Function to log error
log_error() {
    echo -e "${RED}❌ $1${NC}"
    OVERALL_STATUS=1
}

# Function to log warning
log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

###############################################################################
# TEST 1: Complete First-Time Tutorial Flow
###############################################################################

log_step "TEST 1: Complete First-Time Tutorial Flow"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Reset database
log_step "Resetting test database..."
if /var/www/html/scripts/testing/reset_test_database.sh > "${LOG_DIR}/reset_db_test1.log" 2>&1; then
    log_success "Test database reset successfully"
else
    log_error "Failed to reset test database"
    exit 1
fi

# Run test
log_step "Running complete tutorial test..."
if CYPRESS_CONTAINER=true timeout 600 xvfb-run npx cypress run \
    --spec "cypress/e2e/tutorial-production-ready.cy.js" \
    --config video=true \
    > "${LOG_DIR}/test1_output.log" 2>&1; then
    log_success "TEST 1 PASSED - Complete tutorial flow validated"
else
    log_error "TEST 1 FAILED - Check ${LOG_DIR}/test1_output.log"
    OVERALL_STATUS=1
fi

echo ""

###############################################################################
# TEST 2: Resume & Persistence
###############################################################################

log_step "TEST 2: Resume & Persistence"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Reset database again for clean state
log_step "Resetting test database..."
if /var/www/html/scripts/testing/reset_test_database.sh > "${LOG_DIR}/reset_db_test2.log" 2>&1; then
    log_success "Test database reset successfully"
else
    log_error "Failed to reset test database"
    exit 1
fi

# Run test
log_step "Running resume & persistence test..."
if CYPRESS_CONTAINER=true timeout 600 xvfb-run npx cypress run \
    --spec "cypress/e2e/tutorial-resume-persistence.cy.js" \
    --config video=true \
    > "${LOG_DIR}/test2_output.log" 2>&1; then
    log_success "TEST 2 PASSED - Resume & persistence validated"
else
    log_error "TEST 2 FAILED - Check ${LOG_DIR}/test2_output.log"
    OVERALL_STATUS=1
fi

echo ""

###############################################################################
# RESULTS SUMMARY
###############################################################################

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Test Results Summary                                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Find latest screenshot/video directories
SCREENSHOT_DIR=$(find data_tests/cypress/screenshots -type d -name "*tutorial-production-ready*" | tail -1)
VIDEO_DIR=$(find data_tests/cypress/videos -type d | tail -1)

if [ $OVERALL_STATUS -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}║  ✅ ✅ ✅  ALL TESTS PASSED - PRODUCTION READY!  ✅ ✅ ✅    ║${NC}"
    echo -e "${GREEN}║                                                            ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${GREEN}The tutorial system has passed all validation criteria:${NC}"
    echo "  ✅ Complete first-time tutorial flow (30 steps)"
    echo "  ✅ Tutorial resume & persistence"
    echo "  ✅ Movement system validation"
    echo "  ✅ Resource gathering validation"
    echo "  ✅ Combat system validation"
    echo "  ✅ Database state validation"
    echo "  ✅ Session management validation"
    echo ""
    echo -e "${GREEN}Next steps:${NC}"
    echo "  1. Review screenshots: ${SCREENSHOT_DIR}"
    echo "  2. Review videos: ${VIDEO_DIR}"
    echo "  3. Review production deployment checklist in docs/tutorial-production-testing.md"
    echo "  4. Deploy to staging environment for final validation"
    echo "  5. Deploy to production!"
else
    echo -e "${RED}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                                                            ║${NC}"
    echo -e "${RED}║  ❌ ❌ ❌  TESTS FAILED - NOT PRODUCTION READY  ❌ ❌ ❌    ║${NC}"
    echo -e "${RED}║                                                            ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}One or more tests failed. Please:${NC}"
    echo "  1. Review test logs in: ${LOG_DIR}"
    echo "  2. Review screenshots: ${SCREENSHOT_DIR}"
    echo "  3. Review videos: ${VIDEO_DIR}"
    echo "  4. Check troubleshooting guide in docs/tutorial-production-testing.md"
    echo "  5. Fix issues and re-run tests"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Test run timestamp: ${TIMESTAMP}"
echo "Logs saved to: ${LOG_DIR}"
echo "Screenshots: ${SCREENSHOT_DIR}"
echo "Videos: ${VIDEO_DIR}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exit $OVERALL_STATUS
