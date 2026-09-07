# 🚀 Gid Konplè: Deplwaman UpMizik sou Coolify (Hostinger VPS)

Gid sa a montre w etap pa etap kijan pou w lanse UpMizik 100% sou **Coolify**, kreye baz done MySQL la ladan l, retire ansyen sèvis aaPanel yo nèt pou libere pò 80/443 ak memwa RAM, epi genyen yon sistèm otomatik san okenn tèt chaje pèmisyon ankò.

---

## Enfòmasyon Sèvè & Domèn
- **Sèvè VPS**: Hostinger KVM 2 (Ubuntu 22.04 LTS)
- **IP Piblik**: `2.25.132.44`
- **Domèn Prensipal**: `https://upmizik.com` ak `https://www.upmizik.com`
- **GitHub Repozitwa**: `https://github.com/venso509-art/upmiziknew.git`

---

## ETAP 1: Kanpe epi Dezaktive aaPanel pou Libere Pò 80 ak 443

Pou Coolify ak proxy li (Traefik) ka pran kontwòl domèn `upmizik.com` epi jere sètifika SSL otomatikman, nou dwe kanpe Nginx/Apache ak MySQL aaPanel yo ki t ap itilize pò sa yo sou sistèm lame a:

Nan tèminal SSH sèvè a (`root@srv1927985`), tape:

```bash
# 1. Kanpe sèvis aaPanel yo
bt stop

# 2. Kanpe Nginx ak MySQL ki te enstale pa aaPanel
systemctl stop nginx || service nginx stop
systemctl stop mysql || service mysql stop
systemctl disable nginx || true
systemctl disable mysql || true

# 3. Dezaktive aaPanel pou l pa re-demare lè sèvè a re-demare
systemctl disable bt || true

echo "✓ Pò 80, 443 ak 3306 libere avèk siksè pou Coolify!"
```

*(Si w vle retire dosye aaPanel yo nèt pita: `rm -rf /www/server` apre Coolify fin mache 100%).*

---

## ETAP 2: Enstale Coolify sou VPS la (Si l poko enstale)

Si w poko enstale Coolify sou sèvè a, kouri yon sèl kòmand sa a:

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Apre enstalasyon an fini (2-3 minit):
1. Louvri nan navigatè w: `http://2.25.132.44:8000`
2. Kreye kont administratè w la (non, email, modpas).

---

## ETAP 3: Deplwaye UpMizik sou Coolify (Opsyon Docker Compose - Rekòmande)

Pwojè UpMizik la gen deja yon fichye `docker-compose.yml` ak `Dockerfile` optimize ki enkli tout bagay (Frontend Vite, Backend PHP 8.2, Nginx, ak Baz Done MySQL 8.0 avèk tout tablo yo).

1. Nan Dashboard Coolify a, ale nan **Projects** -> klike **+ Create Project** (non: `UpMizik`).
2. Klike sou anviwònman **production**.
3. Klike **+ New Resource** -> Chwazi **Git Source (GitHub)** oswa **Public Repository**.
4. Mete URL repozitwa a:
   ```
   https://github.com/venso509-art/upmiziknew.git
   ```
   Branch: `main` (oswa branch ou itilize a).
5. Chwazi **Docker Compose** kòm Build Pack.
6. Nan paramèt resous la:
   - **Domains for app**: Mete:
     ```
     https://upmizik.com, https://www.upmizik.com
     ```
7. Klike sou **Deploy**!

---

## ETAP 4: Baz Done MySQL sou Coolify

Pwojè a gen de opsyon pou baz done a:

### Opsyon A (Otomatik nan Docker Compose - Pi Fasil):
Sèvis `db` ki nan `docker-compose.yml` la monte yon baz done MySQL 8.0 otomatikman epi li enpòte tout tablo yo (`backend/database/schema.sql`). 
- **DB_HOST**: `db`
- **DB_NAME**: `upmizik_db`
- **DB_USER**: `upmizik_user`
- **DB_PASS**: `upmizik_secure_pass_2026`
- Done yo sovgade nan volim Docker pèmanan `db_data` (yo p ap janm pèdi).

### Opsyon B (Kreye yon Database Standalone nan Coolify):
Si w vle kreye baz done a apa nan Coolify pou w gen yon Dashboard endepandan:
1. Nan pwojè Coolify w la, klike **+ New Resource** -> chwazi **Database** -> **MySQL**.
2. Mete non baz done a: `upmizik_db`.
3. Kopiye non itilizatè, modpas, ak host Coolify ba w la.
4. Nan aplikasyon UpMizik la nan Coolify, ale nan tab **Environment Variables** epi mete:
   ```env
   DB_HOST=<Host Coolify ba ou a>
   DB_PORT=3306
   DB_NAME=upmizik_db
   DB_USER=<User Coolify ba ou a>
   DB_PASS=<Password Coolify ba ou a>
   APP_ENV=production
   ```
5. Enpòte fichye `backend/database/schema.sql` nan baz done a (via phpMyAdmin Coolify oswa terminal MySQL).

---

## ETAP 5: Fichye Mizik ak Fichye Upload (Volim Pèmanan)

Nan Docker, pou mizik MP3, foto kouvèti ak logo atis yo pa janm disparèt lè gen nouvo `git pull` oswa redeploy:
- Volim Docker pèmanan an konfigire nan `docker-compose.yml`:
  ```yaml
  volumes:
    - uploads_data:/var/www/html/backend/uploads
  ```
- Nenpòt atis ki pibliye mizik, fichye a rete an sekirite nan volim `uploads_data` sou sèvè a.

---

## Poukisa Coolify pi bon pase aaPanel pou UpMizik ?
1. **0 Erè Pèmisyon**: Tout bagay kouri andedan bwat Docker la sou itilizatè `www-data`, pa gen `.user.ini` ki bloke, pa gen batay ak `chown`.
2. **Auto-Deploy**: Depi w pouse yon mizajou sou GitHub, Coolify re-konstwi sit la otomatikman.
3. **SSL Otomatik**: Let's Encrypt SSL jere e renouvle otomatikman pa Traefik.
4. **Izolasyon Total**: Si gen yon erè, se sèl veso a ki re-demare, tout rès sistèm lan rete estab.
