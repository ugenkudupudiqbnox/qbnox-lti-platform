#!/usr/bin/env bash
# Pressbooks LTI Platform - Lab Credentials Summary
set -e

# Load environment
source "$(dirname "$0")/load-env.sh"

# Colors for output
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "\n${GREEN}================================================================${NC}"
echo -e "${GREEN}🚀 Pressbooks LTI Development Lab is Ready!${NC}"
echo -e "${GREEN}================================================================${NC}"

echo -e "\n${CYAN}🎓 MOODLE (LMS)${NC}"
echo -e "  URL:      ${YELLOW}${MOODLE_URL}${NC}"
echo -e "  Admin:      ${MOODLE_ADMIN_USER} / ${MOODLE_ADMIN_PASSWORD}"
echo -e "  Instructor: instructor / moodle"
echo -e "  Student:    student / moodle"
echo -e "  Course:     LTI Testing Course (ID: 2)"

echo -e "\n${CYAN}📖 PRESSBOOKS (Tool)${NC}"
echo -e "  URL:      ${YELLOW}${PRESSBOOKS_URL}/wp-admin${NC}"
echo -e "  Admin:    ${PB_ADMIN_USER} / ${PB_ADMIN_PASSWORD}"
echo -e "  Book:     LTI Test Book (${PRESSBOOKS_URL}/test-book)"

echo -e "\n${CYAN}🗄️ INFRASTRUCTURE${NC}"
echo -e "  MySQL:    localhost:3306 (root/root)"
echo -e "  Protocol: ${PROTOCOL}"

echo -e "\n${CYAN}🧪 USEFUL COMMANDS${NC}"
echo -e "  Diagnostics:  bash scripts/doctor.sh"
echo -e "  Test AGS:     make test-ags"
echo -e "  Access Bash:  docker exec -it pressbooks bash"

echo -e "${GREEN}================================================================${NC}\n"
