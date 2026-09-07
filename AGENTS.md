# Pwojè & Enfòmasyon Enpòtan sou Deplwaman (Devops / Hostinger / Coolify)

## Achitekti & Enfòmasyon Sèvè Pwodiksyon
- **Sistèm Operasyon**: **Ubuntu 22.04 LTS**
- **Panèl Jesyon Prensipal**: **Coolify** (Baze sou Docker & Docker Compose)
- **Plan VPS**: KVM 2 (VPS ID: 1927985)
- **Adrès IP Piblik**: `2.25.132.44`
- **Domèn Prensipal**: `upmizik.com` ak `www.upmizik.com`
- **Metòd Deplwaman**: **Docker / Docker Compose via Coolify**
- **Repozitwa GitHub**: `https://github.com/venso509-art/upmiziknew.git`
- **Baz Done**: **MySQL 8.0** sou Coolify (itilize schéma `backend/database/schema.sql`)

## Workflow Senkronizasyon & Deplwaman
1. Kod la devlope epi ajiste sou **Google AI Studio**.
2. AI Studio senkronize dirèkteman ak repozitwa **GitHub** pwojè a.
3. **Coolify** detekte chak commit sou GitHub, li bati imaj Docker a (Vite frontend + PHP 8.2 FPM + Nginx) epi li deplwaye l otomatikman ak sètifika SSL Let's Encrypt.
4. Fichye mizik MP3 ak foto atis yo estoke nan volim pèmanan `uploads_data` pou yo pa janm pèdi.

## Enstriksyon pou Asistan an
- Priyorize achitekti **Coolify + Docker Compose + MySQL** pou tout rekòmandasyon deplwaman ak scripts.
- Nou pa itilize aaPanel ankò pou evite tout tèt chaje pèmisyon ak `.user.ini`.
- Tout konfigirasyon dwe respekte estanda Docker ak Nginx/PHP-FPM ki nan `Dockerfile` ak `docker-compose.yml`.
