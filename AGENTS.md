# Pwojè & Enfòmasyon Enpòtan sou Deplwaman (Devops / Hostinger)

## Achitekti & Enfòmasyon Sèvè Pwodiksyon
- **Sistèm Operasyon**: **Ubuntu 22.04 LTS**
- **Panèl Jesyon**: **aaPanel** sou Ubuntu
- **Plan VPS**: KVM 2 (VPS ID: 1927985)
- **Adrès IP Piblik**: `2.25.132.44`
- **Domèn Prensipal**: `upmizik.com` ak `www.upmizik.com`
- **Web Root Pwojè**: `/www/wwwroot/upmizik.com`
- **Build Frontend (Vite)**: `/www/wwwroot/upmizik.com/dist`

## Workflow Senkronizasyon & Deplwaman
1. Kod la devlope epi ajiste sou **Google AI Studio**.
2. AI Studio senkronize dirèkteman ak repozitwa **GitHub** pwojè a.
3. Sèvè VPS Hostinger a (aaPanel sou Ubuntu 22.04) rale (git pull) mizajou yo soti sou GitHub, fè `npm run build`, epi Nginx sèvi fichye optimize yo ki soti nan `/www/wwwroot/upmizik.com/dist` ak backend PHP a.

## Enstriksyon pou Asistan an
- Toujou kenbe nan tèt ou achitekti sa a (Hostinger VPS + Ubuntu 22.04 + aaPanel + GitHub Sync).
- Tout rekòmandasyon deplwaman, konfigirasyon sèvè (Nginx, PHP-FPM, MySQL, anviwònman `.env`, pèmisyon `www:www`, oswa kòmand terminal) dwe konpatib 100% ak **aaPanel sou Ubuntu 22.04**.
- Pa janm sèvi ak `/home/...`, CyberPanel oswa AlmaLinux kòm referans.
