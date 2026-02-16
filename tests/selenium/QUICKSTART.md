# Quick Start Guide - Selenium UI Testing

Get up and running with Selenium tests in 5 minutes!

---

## 🚀 Quick Setup

### 1. Install Dependencies

```bash
cd /root/qbnox-lti-platform/tests/selenium

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

### 2. Configure Environment

```bash
# Copy example config
cp .env.example .env

# Edit with your credentials
nano .env
```

**Minimum required configuration:**
```bash
MOODLE_URL=https://moodle.lti.qbnox.com
PRESSBOOKS_URL=https://pb.lti.qbnox.com
MOODLE_STUDENT_USER=student
MOODLE_STUDENT_PASSWORD=your_password
```

### 3. Run Smoke Tests

```bash
# Run quick smoke tests (3-5 minutes)
./run_tests.sh -s
```

---

## 📋 Common Commands

```bash
# Run all tests
./run_tests.sh

# Run specific test file
./run_tests.sh -t test_lti_launch.py

# Run in visible mode (watch browser)
./run_tests.sh -v

# Run specific marker
pytest -m h5p -v

# Run single test
pytest test_lti_launch.py::TestLTILaunch::test_student_launch_to_chapter -v
```

---

## 🎯 Test Markers

```bash
pytest -m smoke           # Quick critical tests (5 min)
pytest -m lti_launch      # LTI launch tests
pytest -m h5p             # H5P activity tests
pytest -m deep_linking    # Deep Linking tests
pytest -m grade_sync      # Grade sync tests
```

---

## 📊 View Results

```bash
# HTML report (after tests run)
open reports/report.html

# Screenshots (on failure)
ls screenshots/

# Logs
cat reports/test.log
```

---

## 🐛 Troubleshooting

**Tests failing?**

1. **Check URLs are accessible:**
   ```bash
   curl -I $MOODLE_URL
   curl -I $PRESSBOOKS_URL
   ```

2. **Run in visible mode to debug:**
   ```bash
   ./run_tests.sh -v -t test_lti_launch.py
   ```

3. **Check screenshots:**
   ```bash
   open screenshots/
   ```

4. **Increase timeout:**
   ```bash
   # In .env
   SELENIUM_TIMEOUT=60
   ```

---

## 🔧 Docker (Optional)

```bash
# Start Selenium Grid
docker-compose up -d

# Run tests in grid
docker-compose run test-runner

# View grid dashboard
open http://localhost:4444

# Stop grid
docker-compose down
```

---

## 📚 Next Steps

- Read full [README.md](README.md) for detailed documentation
- Check [Test Structure](#test-structure) to understand organization
- See [Writing New Tests](#writing-new-tests) to add your own tests
- Configure [CI/CD Integration](#cicd-integration) for automated runs

---

**Need Help?**
- Check [Troubleshooting](README.md#troubleshooting) section
- Open issue: https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues

---

**Last Updated:** 2026-02-16
