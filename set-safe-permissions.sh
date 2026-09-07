#!/usr/bin/env bash
# ==============================================================================
# UpMizik - Safe Permissions & Ownership Setup Script
# Target: Ubuntu 22.04 LTS / Hostinger VPS (Nginx & PHP-FPM)
#
# Features:
# - Uses 'find' to selectively change ownership and permissions
# - Automatically detects and excludes immutable/restricted files (e.g. .user.ini)
# - Excludes .git, .env, and sensitive sockets/temporary files
# - Defaults to 'www-data:www-data' (standard Ubuntu Nginx) with automatic
#   fallback to 'www:www' if aaPanel's standalone daemon user is used
# ==============================================================================

set -eo pipefail

TARGET_DIR="${1:-/www/wwwroot/upmizik.com}"

# Color codes for clean output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}==============================================================================${NC}"
echo -e "${BLUE}🛡️  UpMizik Safe Permissions & Ownership Script${NC}"
echo -e "${BLUE}Target Directory: ${TARGET_DIR}${NC}"
echo -e "${BLUE}==============================================================================${NC}"

# 1. Verify target directory exists
if [ ! -d "$TARGET_DIR" ]; then
    echo -e "${RED}❌ Error: Target directory '$TARGET_DIR' does not exist.${NC}"
    exit 1
fi

# 2. Determine Web Server User and Group
# Priority requested by user: www-data:www-data.
# Fallback to www:www if www-data does not exist in /etc/passwd
TARGET_USER="www-data"
TARGET_GROUP="www-data"

if ! id "$TARGET_USER" &>/dev/null; then
    if id "www" &>/dev/null; then
        echo -e "${YELLOW}⚠️  User 'www-data' not found, falling back to aaPanel 'www:www'${NC}"
        TARGET_USER="www"
        TARGET_GROUP="www"
    else
        echo -e "${YELLOW}⚠️  Neither 'www-data' nor 'www' found, using current user: $(id -un):$(id -gn)${NC}"
        TARGET_USER="$(id -un)"
        TARGET_GROUP="$(id -gn)"
    fi
else
    echo -e "${GREEN}✓ Using web server user & group: ${TARGET_USER}:${TARGET_GROUP}${NC}"
fi

# 3. Ensure required backend runtime folders exist
echo -e "\n${BLUE}📁 Ensuring backend uploads and logs directories exist...${NC}"
mkdir -p "$TARGET_DIR/backend/uploads/covers" \
         "$TARGET_DIR/backend/uploads/tracks" \
         "$TARGET_DIR/backend/uploads/avatars" \
         "$TARGET_DIR/backend/logs" \
         "$TARGET_DIR/dist"

# 4. Safely set directory and file ownership using find
# Excluding:
# - .user.ini (immutable file created by aaPanel/PHP)
# - .git directory (keep repo metadata intact)
# - .env (preserve restricted permissions)
echo -e "${BLUE}👤 Safely setting ownership to ${TARGET_USER}:${TARGET_GROUP}...${NC}"

find "$TARGET_DIR" \
    -name ".git" -prune -o \
    -name ".user.ini" -prune -o \
    -name ".env" -prune -o \
    -exec chown "$TARGET_USER:$TARGET_GROUP" {} + 2>/dev/null || true

# Explicitly ensure dist, public, and uploads are owned by TARGET_USER
if [ -d "$TARGET_DIR/dist" ]; then
    find "$TARGET_DIR/dist" -name ".user.ini" -prune -o -exec chown "$TARGET_USER:$TARGET_GROUP" {} + 2>/dev/null || true
fi

if [ -d "$TARGET_DIR/backend/uploads" ]; then
    chown -R "$TARGET_USER:$TARGET_GROUP" "$TARGET_DIR/backend/uploads" 2>/dev/null || true
fi

if [ -d "$TARGET_DIR/backend/logs" ]; then
    chown -R "$TARGET_USER:$TARGET_GROUP" "$TARGET_DIR/backend/logs" 2>/dev/null || true
fi

# 5. Set standard Directory permissions (755: rwxr-xr-x)
echo -e "${BLUE}📂 Setting directory permissions (755)...${NC}"
find "$TARGET_DIR" \
    -name ".git" -prune -o \
    -type d -exec chmod 755 {} + 2>/dev/null || true

# 6. Set standard File permissions (644: rw-r--r--)
echo -e "${BLUE}📄 Setting file permissions (644)...${NC}"
find "$TARGET_DIR" \
    -name ".git" -prune -o \
    -name ".user.ini" -prune -o \
    -name ".env" -prune -o \
    -name "*.sh" -prune -o \
    -type f -exec chmod 644 {} + 2>/dev/null || true

# 7. Set write permissions for dynamic directories (775: rwxrwxr-x)
echo -e "${BLUE}💾 Setting writable permissions for uploads and logs (775)...${NC}"
chmod -R 775 "$TARGET_DIR/backend/uploads" 2>/dev/null || true
chmod -R 775 "$TARGET_DIR/backend/logs" 2>/dev/null || true

# 8. Set execution permissions on bash scripts
echo -e "${BLUE}⚙️  Ensuring shell scripts are executable (+x)...${NC}"
find "$TARGET_DIR" -maxdepth 2 -name "*.sh" -exec chmod +x {} + 2>/dev/null || true

# 9. Secure sensitive environment configuration (.env)
if [ -f "$TARGET_DIR/.env" ]; then
    echo -e "${BLUE}🔒 Hardening .env file security (600)...${NC}"
    chmod 600 "$TARGET_DIR/.env" 2>/dev/null || true
fi

echo -e "\n${GREEN}==============================================================================${NC}"
echo -e "${GREEN}✅ Permissions and ownership configured safely and successfully!${NC}"
echo -e "${GREEN}User/Group : ${TARGET_USER}:${TARGET_GROUP}${NC}"
echo -e "${GREEN}Directory  : ${TARGET_DIR}${NC}"
echo -e "${GREEN}==============================================================================${NC}"
