# 🚀 Gid Deplwaman UpMizik sou Coolify (Hostinger VPS)

## Enfòmasyon Sèvè & Domèn
- **VPS**: Hostinger KVM 2 (Ubuntu 22.04 LTS)
- **IP**: `2.25.132.44`
- **Domèn**: `upmizik.com` ak `www.upmizik.com`
- **GitHub Repozitwa**: `https://github.com/venso509-art/upmiziknew.git`

---

## 1. Kijan pou w enstale Coolify sou VPS la (Kouri nan SSH)

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Apre enstalasyon an fini:
- Ouvri navigatè w sou: `http://2.25.132.44:8000`
- Kreye premye kont Admin ou sou Coolify.

---

## 2. Kijan pou w deplwaye UpMizik sou Coolify (An 3 klik)

1. Nan Dashboard Coolify a, klike sou **"+ Create Project"** (rele l `UpMizik`).
2. Klike sou **"+ New Resource"** > Chwazi **"Git Source (GitHub)"** oswa **"Public Repository"**.
3. Mete lyen repozitwa a: `https://github.com/venso509-art/upmiziknew.git`
4. Nan konfigirasyon an:
   - **Build Pack**: Chwazi **Docker Compose** (Coolify ap detekte fichye `docker-compose.yml` la otomatikman).
   - **Domains**: Mete `https://upmizik.com, https://www.upmizik.com`.
5. Klike sou **"Deploy"**!

---

## 3. Avantaj Konfigirasyon Sa a
- **Otomatik**: Chak fwa ou fè mizajou sou GitHub, Coolify fè deplwaman an san w pa bezwen tape okenn kòmand ankò.
- **SSL Otomatik**: Let's Encrypt SSL sètifika renouvle otomatikman.
- **Depo Pèmanan (Volumes)**: Tout mizik MP3 ak foto atis yo estoke nan volim Docker pèmanan (`uploads_data`), yo p ap janm efase.
- **Baz Done MySQL Entegre**: Baz done a ak schema inisyal la chaje otomatikman nan menm VPS la.
