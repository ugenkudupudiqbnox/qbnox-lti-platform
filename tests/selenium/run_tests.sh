#!/bin/bash

# Selenium Test Runner Script
# Usage: ./run_tests.sh [options]

set -e

# Default values
TEST_PATTERN="test_*.py"
BROWSER="chrome"
HEADLESS="true"
PARALLEL=1

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Help message
show_help() {
    cat << EOF
Selenium Test Runner for Qbnox LTI Platform

Usage: ./run_tests.sh [OPTIONS]

OPTIONS:
    -h, --help              Show this help message
    -t, --test PATTERN      Test pattern to run (default: test_*.py)
    -b, --browser BROWSER   Browser to use: chrome, firefox (default: chrome)
    -v, --visible           Run in visible mode (not headless)
    -p, --parallel N        Run tests in parallel (N workers)
    -s, --smoke             Run smoke tests only
    -m, --marker MARKER     Run tests with specific marker

EXAMPLES:
    # Run all tests
    ./run_tests.sh

    # Run specific test file
    ./run_tests.sh -t test_lti_launch.py

    # Run in visible mode (watch browser)
    ./run_tests.sh -v

    # Run smoke tests only
    ./run_tests.sh -s

    # Run tests in parallel (4 workers)
    ./run_tests.sh -p 4

    # Run tests with specific marker
    ./run_tests.sh -m h5p

EOF
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -t|--test)
            TEST_PATTERN="$2"
            shift 2
            ;;
        -b|--browser)
            BROWSER="$2"
            shift 2
            ;;
        -v|--visible)
            HEADLESS="false"
            shift
            ;;
        -p|--parallel)
            PARALLEL="$2"
            shift 2
            ;;
        -s|--smoke)
            TEST_PATTERN="-m smoke"
            shift
            ;;
        -m|--marker)
            TEST_PATTERN="-m $2"
            shift 2
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            show_help
            exit 1
            ;;
    esac
done

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}Warning: .env file not found. Creating from .env.example...${NC}"
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${YELLOW}Please edit .env with your credentials before running tests.${NC}"
        exit 1
    else
        echo -e "${RED}Error: .env.example not found${NC}"
        exit 1
    fi
fi

# Export environment variables
export SELENIUM_BROWSER="$BROWSER"
export SELENIUM_HEADLESS="$HEADLESS"

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Selenium Test Runner${NC}"
echo -e "${GREEN}================================${NC}"
echo "Browser: $BROWSER"
echo "Headless: $HEADLESS"
echo "Test pattern: $TEST_PATTERN"
echo "Parallel workers: $PARALLEL"
echo -e "${GREEN}================================${NC}"
echo ""

# Create directories
mkdir -p screenshots reports

# Check if Python virtual environment exists
if [ ! -d "venv" ]; then
    echo -e "${YELLOW}Creating Python virtual environment...${NC}"
    python3 -m venv venv
fi

# Activate virtual environment
source venv/bin/activate

# Install/update dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
pip install -q -r requirements.txt

# Run tests
echo -e "${GREEN}Running tests...${NC}"
echo ""

if [ "$PARALLEL" -gt 1 ]; then
    # Run in parallel
    pytest $TEST_PATTERN -n $PARALLEL
else
    # Run sequentially
    pytest $TEST_PATTERN
fi

# Check test results
TEST_EXIT_CODE=$?

echo ""
echo -e "${GREEN}================================${NC}"

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed${NC}"
    echo -e "${YELLOW}Check reports/report.html for details${NC}"
    echo -e "${YELLOW}Check screenshots/ for failure screenshots${NC}"
fi

echo -e "${GREEN}================================${NC}"

# Open HTML report (optional - comment out if not needed)
if command -v xdg-open &> /dev/null; then
    echo -e "${YELLOW}Opening test report...${NC}"
    xdg-open reports/report.html 2>/dev/null || true
fi

exit $TEST_EXIT_CODE
