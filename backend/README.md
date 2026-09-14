# UpMizik - Gid Deplwaman sou VPS Hostinger avèk Coolify & Docker

Gid sa a eksplike kijan pou mete ak jere UpMizik an liy sou VPS Hostinger ou an avèk **Coolify** (ki baze sou Docker, Docker Compose, ak MySQL 8.0).

---

## 1. Achitekti Sèvè & Pwodiksyon

- **VPS**: Hostinger KVM 2 (VPS ID: 1927985, IP: `2.25.132.44`)
- **Sistèm**: Ubuntu 22.04 LTS
- **Panèl Jesyon**: **Coolify**
- **Metòd Deplwaman**: Docker Compose / Dockerfile
- **Domèn Prensipal**: `https://upmizik.com` ak `https://www.upmizik.com`
- **Frontend**: React 19 + TypeScript + Vite + Tailwind CSS (Bati nan imaj Docker, distribye sou Nginx)
- **Backend**: PHP 8.2 FPM / API PDO MySQL (`/backend/api/`)
- **Baz Done**: MySQL 8.0 sou Coolify (Schéma: `backend/database/schema.sql`)
- **Fichye Upload**: Volim pèmanan Docker `uploads_data` (`/var/www/html/backend/uploads/`) pou mizik MP3, kouvèti, ak foto pa janm pèdi.

---

## 2. Deplwaman Otomatik via Coolify & GitHub

1. Nan **Coolify Dashboard** (`http://2.25.132.44:8000` oswa domèn Coolify ou):
   - Chwazi **Projects** > **UpMizik**.
   - Ajoute yon nouvo resous tip **Application** oswa **Docker Compose**.
   - Konekte dirèkteman ak repozitwa GitHub: `https://github.com/venso509-art/upmiziknew.git`.
   - Branch: `main`.
2. **Build Pack**: Chwazi **Docker Compose** (itilize `docker-compose.yml`) oswa **Dockerfile** (itilize `Dockerfile`).
3. **Automated Deployments**: Aktive **Webhook / Auto-deploy on Push** pou chak commit sou GitHub deplwaye otomatikman.

---

## 3. Varyab Anviwònman sou Coolify (.env)

Konfigire varyab sa yo dirèkteman nan panèl **Environment Variables** sou Coolify:

```env
# Frontend
VITE_API_BASE_URL=/backend/api
VITE_PHP_API_URL=/backend/api

# Baz Done MySQL (sèvis Docker 'db' oswa baz Coolify dedye)
DB_HOST=db
DB_PORT=3306
DB_NAME=upmizik_db
DB_USER=upmizik_user
DB_PASS=upmizik_secure_pass_2026

# Anviwònman & Domèn
APP_ENV=production
SITE_URL=https://upmizik.com
ALLOWED_ORIGINS=https://upmizik.com,https://www.upmizik.com

# Administratè Master
ADMIN_EMAIL=admin@upmizik.com
ADMIN_SECRET=UpMizikAdmin2026SecureKey!

# MonCash & Natcash
MONCASH_CLIENT_ID=
MONCASH_CLIENT_SECRET=
MONCASH_ENVIRONMENT=sandbox
```

---

## 4. Volim Pèmanan pou Fichye Mizik (MP3 & Imaj)

Pou asire mizik ak imaj atis yo rete an sekirite menm lè Docker rekòmanse:
- Volim `uploads_data` monte sou `/var/www/html/backend/uploads`
- Volim `db_data` monte sou `/var/lib/mysql` pou done baz done a

---

## 5. Inisyalizasyon Tablo MySQL

Lè sèvis la monte pou premye fwa, `docker-compose.yml` egzekite `backend/database/schema.sql` otomatikman nan `/docker-entrypoint-initdb.d/init.sql`.

Si ou bezwen egzekite l manyèlman nan terminal VPS la:
```bash
docker exec -i upmizik-db mysql -u upmizik_user -p upmizik_db < backend/database/schema.sql
```

---

## 6. Verifikasyon Sèvis la

1. Vizite `https://upmizik.com`
2. Teste API Health Check: `https://upmizik.com/backend/api/health.php`
3. Teste koneksyon admin nan aplikasyon an avèk kont administratè ou.
