#!/bin/bash
# ==============================================================================
# UpMizik - Script Enstalasyon & Deplwaman Otomatik (Yon Sèl Kòmand)
# Pou Ubuntu 22.04 LTS + aaPanel (Hostinger VPS)
# ==============================================================================

set -e

echo "========================================================"
echo "🚀 KÒMANSE ENSTALASYON & DEPLWAMAN OTIOMATIK UPMIZIK..."
echo "========================================================"

WEB_ROOT="/www/wwwroot/upmizik.com"

# 1. Asire w nan bon dosye a
if [ ! -d "$WEB_ROOT" ]; then
    echo "📁 Dosye $WEB_ROOT pa egziste. Klonen repozitwa a..."
    mkdir -p /www/wwwroot
    cd /www/wwwroot
    git clone https://github.com/venso509-art/upmiziknew.git upmizik.com
fi

cd $WEB_ROOT

# 2. Rale dènye kòd ki sou GitHub la
echo "⬇️ 1/5: Ap rale dènye vèsyon kòd la sou GitHub..."
git pull origin main || true

# 3. Kreye fichye .env si l pa egziste
if [ ! -f "$WEB_ROOT/.env" ]; then
    echo "⚙️ 2/5: Ap kreye fichye .env inisyal..."
    cp .env.example .env || true
fi

# 4. Asire Node.js ak npm enstale
if ! command -v npm &> /dev/null; then
    echo "📦 Node.js pa jwenn. Ap enstale Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# 5. Enstale depandans yo epi fè Build Production lan
echo "🔨 3/5: Ap enstale depandans ak fè Build Vite (dist)..."
# aaPanel mete .user.ini pwoteje (immutable) nan dist, retire li pou Vite ka bati lib
if [ -f "$WEB_ROOT/dist/.user.ini" ]; then
    chattr -i "$WEB_ROOT/dist/.user.ini" 2>/dev/null || true
    rm -f "$WEB_ROOT/dist/.user.ini" 2>/dev/null || true
fi
npm install
npm run build

# 6. Kreye dosye uploads ak logs si yo pa la
echo "📁 4/5: Ap verifye dosye uploads ak logs..."
mkdir -p $WEB_ROOT/backend/uploads/covers
mkdir -p $WEB_ROOT/backend/uploads/tracks
mkdir -p $WEB_ROOT/backend/uploads/avatars
mkdir -p $WEB_ROOT/backend/logs

# 7. Ranje tout pèmisyon yo nèt (www:www)
echo "🔒 5/5: Ap aplike bon pèmisyon sekirite (www:www)..."
sudo chown -R www:www $WEB_ROOT
sudo find $WEB_ROOT -type d -exec chmod 755 {} \;
sudo find $WEB_ROOT -type f -exec chmod 644 {} \;
sudo chmod -R 775 $WEB_ROOT/backend/uploads
sudo chmod -R 775 $WEB_ROOT/backend/logs
chmod +x $WEB_ROOT/auto-deploy.sh || true

echo "========================================================"
echo "✅ TOUT BAGAY FIN PARE E DEPLWAYE AVÈK SIKSÈ!"
echo "🌐 Sit ou a disponib sou: https://upmizik.com"
echo "========================================================"
