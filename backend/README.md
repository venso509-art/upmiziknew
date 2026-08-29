# UpMizik - Gid Deplwaman sou Hostinger VPS (Ubuntu 22.04 + aaPanel)

Gid sa a eksplike etap pa etap kijan pou konfigire ak mete UpMizik an liy sou VPS Hostinger ou an avèk panèl **aaPanel**.

---

## 1. Achitekti Pwojè a

- **Frontend (Kliyan)**: React 19 + TypeScript + Vite + Tailwind CSS (Bati nan dosye `dist/`)
- **Backend (API)**: PHP 8.1+ avèk PDO MySQL (Sitiye nan dosye `backend/api/`)
- **Baz Done**: MySQL 8.0+ (Schema nan `backend/database/schema.sql`)
- **Fichye Upload**: `backend/uploads/` (Mizik MP3, Kouvèti, Prèv transfè)
- **Sekirite**: Middleware CORS, Sessions HttpOnly, CSRF Token, Rate Limiting, `.htaccess` pwoteksyon.

---

## 2. Etap Konfigirasyon Baz Done sou aaPanel

1. Konekte sou **aaPanel** (`https://ip_vps_ou:8888`).
2. Ale nan **Databases** > **Add Database**:
   - **DBName**: `u123456789_upmizik` (oswa non ou chwazi a)
   - **DBUser**: `u123456789_upmizik_user`
   - **Password**: Jenere yon modpas solid (egz: `VotreMotDePasseSekirize509@`)
   - **Charset**: `utf8mb4`
3. Klike sou **phpMyAdmin** bò kote baz done a oswa louvri terminal VPS la:
   ```bash
   mysql -u u123456789_upmizik_user -p u123456789_upmizik < /www/wwwroot/upmizik.com/backend/database/schema.sql
   ```

---

## 3. Konfigirasyon Fichye `.env`

Nan rasin sit la (`/www/wwwroot/upmizik.com/.env`), kreye fichye `.env` an:

```env
# Frontend
VITE_API_BASE_URL=/backend/api
VITE_PHP_API_URL=/backend/api

# Baz Done MySQL
DB_HOST=localhost
DB_PORT=3306
DB_NAME=non_baz_done_ou_a
DB_USER=non_itilizate_baz_done_ou_a
DB_PASS=Mete_Yon_Modpas_Solid_Isit_La

# Anviwònman & Domèn
APP_ENV=production
SITE_URL=https://upmizik.com
ALLOWED_ORIGINS=https://upmizik.com,https://www.upmizik.com

# Administratè Master
ADMIN_EMAIL=admin@upmizik.com
ADMIN_SECRET=Mete_Yon_Sekre_Admin_Solid_Isit_La

# MonCash Gateway (Opsyonèl pou kòmanse)
MONCASH_CLIENT_ID=
MONCASH_CLIENT_SECRET=
MONCASH_ENVIRONMENT=sandbox
MONCASH_RETURN_URL=https://upmizik.com/backend/api/donations.php?action=return
MONCASH_CANCEL_URL=https://upmizik.com/backend/api/donations.php?action=cancel
```

---

## 4. Konfigirasyon Nginx sou aaPanel

Nan aaPanel > **Websites** > Klike sou sit ou an > **URL rewrite** oswa **Configuration File**:

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name upmizik.com www.upmizik.com;
    root /www/wwwroot/upmizik.com;
    index index.html index.php;

    # Limit Upload pou mizik MP3
    client_max_body_size 128M;

    # 1. API PHP Routes
    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$query_string;
    }

    # 2. Ekzekisyon Script PHP
    location ~ \.php$ {
        include enable-php-81.conf; # oswa enable-php-82.conf
        fastcgi_pass unix:/tmp/php-cgi-81.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 3. Sekirite Dosye Uploads (Pa kite PHP kouri nan uploads)
    location /backend/uploads/ {
        location ~ \.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|sh)$ {
            deny all;
        }
    }

    # 4. Bloke aksè sou dosye sekrè
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
    location /backend/backups/ {
        deny all;
    }
    location /backend/logs/ {
        deny all;
    }

    # 5. Single Page Application (React Router fallback)
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

---

## 5. Pèmisyon Dosye sou Ubuntu

Egzekite kòmand sa yo nan terminal VPS la pou bay bon pèmisyon pou Apache/Nginx (`www` oswa `www-data`):

```bash
cd /www/wwwroot/upmizik.com
chown -R www:www .
chmod -R 755 backend/uploads
chmod -R 750 backend/backups
chmod -R 750 backend/logs
chmod 600 .env
```

---

## 6. Bati Frontend la (Build Vite)

Pou mete tout dènye chanjman React yo an liy:

```bash
cd /www/wwwroot/upmizik.com
npm install
npm run build
cp -r dist/* .
```

---

## 7. Backup Otomatik (Cron Job sou aaPanel)

Ale nan **aaPanel** > **Cron** > **Add Task**:
- **Type**: Shell Script
- **Name**: UpMizik Daily Database Backup
- **Period**: Chak jou a 2:00 AM
- **Script**:
  ```bash
  /bin/bash /www/wwwroot/upmizik.com/backend/scripts/backup.sh
  ```

---

## 8. Verifikasyon Final

1. Louvri `https://upmizik.com` nan navigatè a.
2. Ale sou paj login admin: `https://upmizik.com/#/admin` (oswa bouton administratè).
3. Konekte ak:
   - **Email / Username**: `admin` oswa `admin@upmizik.com`
   - **Modpas**: `AdminUpMizik2026Secure!`
