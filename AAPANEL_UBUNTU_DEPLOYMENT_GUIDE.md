# GID KONPLET POU DEPLWAMEN UPMIZIK SOU AAPANEL / UBUNTU 22.04 LTS (HOSTINGER VPS)

## 📌 Enfòmasyon Sèvè Pwodiksyon
- **Sistèm Operasyon**: Ubuntu 22.04 LTS
- **Panèl Jesyon**: aaPanel
- **VPS ID**: 1927985 (Plan KVM 2)
- **Adrès IP Piblik**: `2.25.132.44`
- **Domèn Prensipal**: `upmizik.com`
- **Domèn Segondè**: `www.upmizik.com`
- **Rasin Pwojè a (Web Root)**: `/www/wwwroot/upmizik.com`
- **Diferans Rasin Sit (Vite Build)**: `/www/wwwroot/upmizik.com/dist`

---

## 7. DNS A Records (Sou Hostinger oswa Cloudflare)
Anvan w enstale SSL, konfigire DNS domèn ou a konsa :

| Kalite (Type) | Non (Name) | Valè (Target / Value) | TTL |
| :--- | :--- | :--- | :--- |
| **A** | `@` (upmizik.com) | `2.25.132.44` | Auto / 3600 |
| **A** | `www` | `2.25.132.44` | Auto / 3600 |

*(Tann 5 a 15 minit pou DNS la pwopaje).*

---

## 1. Konfigirasyon Sit la nan aaPanel (Website Configuration)
1. Konekte nan **aaPanel** (`http://2.25.132.44:8888/` oswa lyen sekirize ou a).
2. Klike sou **Website** nan meni bò gòch la > klike sou **Add Site**.
3. Ranpli fòmilè a:
   - **Domain name**: 
     ```
     upmizik.com
     www.upmizik.com
     ```
   - **Description**: UpMizik Production
   - **Root directory**: `/www/wwwroot/upmizik.com`
   - **FTP**: Pa kreye (ou gen SSH)
   - **Database**: MySQL (Kreye l kounye a oswa nan etap 4)
   - **PHP Version**: `PHP-8.1` oswa `PHP-8.2`
4. Klike sou **Submit**.

---

## 3. Vèsyon PHP & Modil PHP-FPM
Nan aaPanel > **App Store**:
1. Enstale **PHP 8.1** oswa **PHP 8.2** (avèk PHP-FPM).
2. Klike sou **Setting** akote PHP 8.1/8.2:
   - Ale nan **Install extensions** > Enstale: `pdo_mysql`, `mysqli`, `curl`, `fileinfo`, `mbstring`, `openssl`, `gd`.
   - Ale nan **Upload size** > Mete:
     - `upload_max_filesize` = `64M` (pou mizik/albòm)
     - `post_max_size` = `64M`
     - `memory_limit` = `256M`
     - `max_execution_time` = `300`
   - Ale nan **Service** > Klike **Restart PHP**.

---

## 4. Baz Done MySQL nan aaPanel
1. Nan aaPanel, ale nan **Databases** > klike **Add Database**.
2. Mete:
   - **DBName**: `upmizik_db`
   - **Username**: `upmizik_user`
   - **Password**: *(Mete yon modpas solid, pa egzanp: `UpMizik$2026SecurePass`)*
3. Enpòte estrikti tab yo:
   - Klike sou **Import** oswa louvri **phpMyAdmin** > Chwazi baz `upmizik_db` > Klike **Import** epi chwazi fichye `/www/wwwroot/upmizik.com/backend/database/schema.sql`.

---

## 10. Deplwaman Kòd la soti nan GitHub pou rive nan aaPanel
Konekte sou VPS ou a via SSH nan terminal Ubuntu 22.04:

```bash
# 1. Ale nan dosye sit la
cd /www/wwwroot

# 2. Si dosye a vid oswa w ap rale repozitwa a pou premye fwa:
git clone https://github.com/ITDEV-VENSO/UpMizik.git upmizik.com
cd upmizik.com

# 3. Kreye fichye anviwònman .env la
cp .env.example .env
nano .env
```
Nan `.env` la, mete bon enfòmasyon baz done MySQL ou te kreye nan aaPanel:
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=upmizik_db
DB_USER=upmizik_user
DB_PASS=UpMizik$2026SecurePass
SITE_URL=https://upmizik.com
APP_ENV=production
ADMIN_EMAIL=admin@upmizik.com
ADMIN_SECRET=ChwaziSekreAdminPaW!
```

Kounye a, compile Frontend Vite a:
```bash
# 4. Enstale Node.js 18+ oswa 20+ si w poko genyen l sou Ubuntu:
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 5. Enstale depandans yo epi fè build production lan:
cd /www/wwwroot/upmizik.com
npm install
npm run build
```
*(Sa ap kreye tout fichye optimize yo nan `/www/wwwroot/upmizik.com/dist`)*.

---

## 2. Konfigirasyon Nginx nan aaPanel (Nginx Configuration)
Nan aaPanel > **Website** > Klike sou **Settings** pou `upmizik.com`:

### A. Site Directory (Root)
- Ale nan onglet **Site directory**:
  - **Site directory**: `/www/wwwroot/upmizik.com`
  - **Running directory**: Chwazi `/dist` epi klike **Save**.

### B. Nginx Config File (Konfigirasyon Konplè)
Ale nan onglet **Configuration** nan menm fenèt la, epi mete blòk sa a:

```nginx
server {
    listen 80;
    server_name upmizik.com www.upmizik.com;
    index index.html index.php;
    root /www/wwwroot/upmizik.com/dist;

    # Limit Upload pou fichye odyo ak kouvèti
    client_max_body_size 64M;

    # Gzip Compression pou vitès maksimòm
    gzip on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # Sekirite: Bloke aksè dirèk ak dosye sansib
    location ~ /\.(env|git|htaccess|sql) {
        deny all;
        return 404;
    }

    # 1. Routing API Backend (PHP-FPM)
    # Lè yon rekèt fèt sou /api/..., Nginx voye l sou /backend/api/index.php
    location /api {
        root /www/wwwroot/upmizik.com;
        try_files $uri $uri/ /backend/api/index.php?$query_string;

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/tmp/php-cgi-81.sock; # Chanje pou 82 si w sou PHP 8.2
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME /www/wwwroot/upmizik.com$fastcgi_script_name;
            fastcgi_read_timeout 300;
        }
    }

    # 2. Dosye Uploads (Mizik ak Foto yo)
    location /uploads {
        alias /www/wwwroot/upmizik.com/backend/uploads;
        expires 30d;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # 3. Fichye Estatik Frontend (Vite Assets)
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    # 4. Frontend Single Page Application (SPA Fallback)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Log files
    access_log /www/wwwlogs/upmizik.com.log;
    error_log /www/wwwlogs/upmizik.com.error.log;
}
```
Klike sou **Save**.

---

## 5 & 6. SSL Let's Encrypt & Redirect HTTP pou HTTPS
1. Nan aaPanel > **Website** > Klike **Settings** pou `upmizik.com`.
2. Ale nan onglet **SSL** > Klike sou sous-onglet **Let's Encrypt**.
3. Koche tou de domèn yo:
   - `upmizik.com`
   - `www.upmizik.com`
4. Klike **Apply**.
5. Apre li fin jenere sètifika a avèk siksè, aktive bouton **Force HTTPS** (sa ap fè tout moun k ap antre sou `http://` otomatikman redireksyone sou `https://`).

---

## 8. Permissions pou Dosye `/www/wwwroot/upmizik.com`
Pou asire Nginx ak PHP ka li epi ekri fichye upload (mizik, foto kouvèti) san erè 403 oswa 500:

Egzekite kòmand sa yo nan terminal SSH:

```bash
# Mete itilizatè www (ki se itilizatè Nginx/aaPanel sou Ubuntu) kòm mèt tout dosye yo:
sudo chown -R www:www /www/wwwroot/upmizik.com

# Otorize lekti ak ekriti estanda:
sudo find /www/wwwroot/upmizik.com -type d -exec chmod 755 {} \;
sudo find /www/wwwroot/upmizik.com -type f -exec chmod 644 {} \;

# Bay dosye uploads ak logs pèmisyon ekriti espesyal pou PHP:
sudo chmod -R 775 /www/wwwroot/upmizik.com/backend/uploads
sudo chmod -R 775 /www/wwwroot/upmizik.com/backend/logs
```

---

## 9. Ki kote pou w tcheke Logs yo (Debugging)

### A. Nginx Logs:
- **Aksè (Access)**: `/www/wwwlogs/upmizik.com.log`
- **Erè Nginx (Error)**: `/www/wwwlogs/upmizik.com.error.log`
- Pou gade yo an dirèk nan terminal:
  ```bash
  tail -f /www/wwwlogs/upmizik.com.error.log
  ```

### B. PHP-FPM Logs:
- **PHP Error Log**: `/www/server/php/81/var/log/php-fpm.log` (oswa `/www/server/php/82/...`)
- **Slow Log**: `/www/server/php/81/var/log/slow.log`

---

## 🔄 Script pou Mizajou Rapid (GitHub Update Script)
Chak fwa w fè chanjman sou GitHub, ou ka jis kouri ti script sa a sou sèvè VPS la pou mete sit la ajou an 10 segonn:

Kreye fichye `deploy.sh`:
```bash
nano /www/wwwroot/upmizik.com/deploy.sh
```
Mete sa ladan l:
```bash
#!/bin/bash
echo "🚀 Kòmanse Mizajou UpMizik..."
cd /www/wwwroot/upmizik.com
git pull origin main
npm install
npm run build
sudo chown -R www:www /www/wwwroot/upmizik.com/dist
sudo chown -R www:www /www/wwwroot/upmizik.com/backend/uploads
echo "✅ Sit la ajou sou https://upmizik.com !"
```

Bay script la pèmisyon ekzekisyon:
```bash
chmod +x /www/wwwroot/upmizik.com/deploy.sh
```

Pou w mete sit la ajou, w ap jis kouri:
```bash
/www/wwwroot/upmizik.com/deploy.sh
```
