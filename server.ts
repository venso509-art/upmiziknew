import express from 'express';
import http from 'http';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// Body parsing with generous limits for media uploads
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// CORS middleware
app.use((req, res, next) => {
  const origin = req.headers.origin || '*';
  res.header('Access-Control-Allow-Origin', origin);
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-CSRF-Token');
  res.header('Access-Control-Allow-Credentials', 'true');
  if (req.method === 'OPTIONS') {
    return res.sendStatus(204);
  }
  next();
});

// Uploads directory
const UPLOADS_DIR = path.join(__dirname, 'backend', 'uploads');
const DATA_DIR = path.join(__dirname, 'backend', 'data');
const STORE_FILE = path.join(DATA_DIR, 'store.json');

if (!fs.existsSync(UPLOADS_DIR)) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}
if (!fs.existsSync(path.join(UPLOADS_DIR, 'proofs'))) {
  fs.mkdirSync(path.join(UPLOADS_DIR, 'proofs'), { recursive: true });
}
if (!fs.existsSync(path.join(UPLOADS_DIR, 'avatars'))) {
  fs.mkdirSync(path.join(UPLOADS_DIR, 'avatars'), { recursive: true });
}
if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

// Serve uploads and public assets statically
app.use('/backend/uploads', express.static(UPLOADS_DIR));
app.use('/uploads', express.static(UPLOADS_DIR));
app.use(express.static(path.join(__dirname, 'public')));

app.get('/robots.txt', (req, res) => {
  res.type('text/plain').sendFile(path.join(__dirname, 'public', 'robots.txt'));
});
app.get('/sitemap.xml', (req, res) => {
  res.type('application/xml').sendFile(path.join(__dirname, 'public', 'sitemap.xml'));
});

// Helper: Read/Write Store
interface AppStore {
  artists: any[];
  musics: any[];
  donations: any[];
  rpa: any[];
  pubs: any[];
  socialPosts: any[];
  settings: any;
}

function getStore(): AppStore {
  try {
    if (fs.existsSync(STORE_FILE)) {
      const content = fs.readFileSync(STORE_FILE, 'utf-8');
      const parsed = JSON.parse(content);
      return {
        artists: Array.isArray(parsed.artists) ? parsed.artists : [],
        musics: Array.isArray(parsed.musics) ? parsed.musics : [],
        donations: Array.isArray(parsed.donations) ? parsed.donations : [],
        rpa: Array.isArray(parsed.rpa) ? parsed.rpa : [],
        pubs: Array.isArray(parsed.pubs) ? parsed.pubs : [],
        socialPosts: Array.isArray(parsed.socialPosts) ? parsed.socialPosts : [],
        settings: parsed.settings || {
          artistRegistrationFeeUsd: 4.99,
          artistRegistrationFeeHtg: 650,
          htgExchangeRate: 132,
          methods: [
            { id: 'moncash', name: 'MonCash', enabled: true, number: '509 3788-0000', merchantName: 'UpMizik Ofisyèl' },
            { id: 'natcash', name: 'Natcash', enabled: true, number: '509 4200-0000', merchantName: 'UpMizik Ofisyèl' }
          ]
        }
      };
    }
  } catch (err) {
    console.error('Error reading store file:', err);
  }
  return {
    artists: [],
    musics: [],
    donations: [],
    rpa: [],
    pubs: [],
    socialPosts: [],
    settings: {
      artistRegistrationFeeUsd: 4.99,
      artistRegistrationFeeHtg: 650,
      htgExchangeRate: 132,
      methods: [
        { id: 'moncash', name: 'MonCash', enabled: true, number: '509 3788-0000', merchantName: 'UpMizik Ofisyèl' },
        { id: 'natcash', name: 'Natcash', enabled: true, number: '509 4200-0000', merchantName: 'UpMizik Ofisyèl' }
      ]
    }
  };
}

function saveStore(data: AppStore) {
  try {
    fs.writeFileSync(STORE_FILE, JSON.stringify(data, null, 2), 'utf-8');
  } catch (err) {
    console.error('Error saving store file:', err);
  }
}

// ==========================================
// 1. HEALTH CHECK
// ==========================================
app.get(['/backend/api/health.php', '/api/health'], (req, res) => {
  res.json({
    success: true,
    status: 'healthy',
    message: 'UpMizik API aktif epi operasyonèl',
    time: new Date().toISOString()
  });
});

// ==========================================
// 2. FILE & BASE64 UPLOADS
// ==========================================
app.post(['/backend/api/upload.php', '/api/upload'], (req, res) => {
  try {
    const { base64Data, type = 'proofs' } = req.body;
    if (!base64Data) {
      return res.status(400).json({ success: false, message: 'Pa gen done fichye transmèt.' });
    }

    const matches = base64Data.match(/^data:image\/([a-zA-Z0-9\+\.-]+);base64,(.+)$/);
    if (!matches) {
      return res.json({ success: true, url: base64Data });
    }

    let ext = matches[1].toLowerCase();
    if (ext === 'jpeg') ext = 'jpg';
    const buffer = Buffer.from(matches[2], 'base64');

    const subDir = type === 'avatars' ? 'avatars' : 'proofs';
    const targetDir = path.join(UPLOADS_DIR, subDir);
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }

    const fileName = `${type}_${Date.now()}_${Math.random().toString(36).substring(2, 8)}.${ext}`;
    const filePath = path.join(targetDir, fileName);
    fs.writeFileSync(filePath, buffer);

    const publicUrl = `/backend/uploads/${subDir}/${fileName}`;
    return res.json({
      success: true,
      url: publicUrl,
      message: 'Telechajman reyisi!'
    });
  } catch (err: any) {
    console.error('Upload error:', err);
    return res.status(500).json({ success: false, message: err?.message || 'Erè telechajman' });
  }
});

// ==========================================
// 3. ARTISTS API
// ==========================================
app.all(['/backend/api/artists.php', '/api/artists'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    const { id, status } = req.query;
    if (id) {
      const art = store.artists.find((a: any) => a.id === id);
      if (!art) {
        return res.status(404).json({ success: false, message: 'Atis la pa jwenn' });
      }
      return res.json({ success: true, data: { artist: art }, artist: art });
    }

    let list = [...store.artists];
    if (status && status !== 'all') {
      list = list.filter((a: any) => a.status === status);
    }
    return res.json({
      success: true,
      data: { artists: list, count: list.length },
      artists: list,
      count: list.length
    });
  }

  if (req.method === 'POST') {
    const data = req.body;
    if (!data.name || !data.stageName || !data.email || !data.phone) {
      return res.status(400).json({
        success: false,
        message: 'Non, Non Sèn, Imèl ak Nimewo Telefòn obligatwa.'
      });
    }

    const cleanEmail = (data.email || '').trim().toLowerCase();
    const existingIndex = store.artists.findIndex(
      (a: any) => (a.email || '').trim().toLowerCase() === cleanEmail
    );

    // Si imèl la deja itilize sou yon lòt kont
    if (existingIndex >= 0) {
      const existing = store.artists[existingIndex];
      if (data.id && existing.id !== data.id) {
        return res.status(409).json({
          success: false,
          message: 'Imèl sa a deja itilize sou yon lòt kont atis'
        });
      }
      if (!data.id && existing.id) {
        return res.status(409).json({
          success: false,
          message: 'Imèl sa a deja itilize sou yon lòt kont atis'
        });
      }
    }

    const artistId = data.id || `art_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`;
    const newArtist = {
      ...data,
      id: artistId,
      email: cleanEmail,
      status: data.status || 'pending',
      registrationDate: data.registrationDate || new Date().toISOString().split('T')[0],
      totalListens: data.totalListens || 0,
      totalDonationsReceived: data.totalDonationsReceived || 0
    };

    if (existingIndex >= 0) {
      store.artists[existingIndex] = { ...store.artists[existingIndex], ...newArtist };
    } else {
      store.artists.unshift(newArtist);
    }

    saveStore(store);

    return res.status(201).json({
      success: true,
      message: 'Enskripsyon atis la fèt avèk siksè nan baz done a!',
      data: {
        artistId,
        registrationProofUrl: newArtist.registrationProofUrl,
        avatarUrl: newArtist.avatarUrl
      },
      artistId,
      registrationProofUrl: newArtist.registrationProofUrl,
      avatarUrl: newArtist.avatarUrl
    });
  }

  if (req.method === 'PUT') {
    const { id, status, registrationRejectionReason, deleteSongs } = req.body;
    const targetId = id || req.query.id;
    if (!targetId) {
      return res.status(400).json({ success: false, message: 'ID obligatwa' });
    }

    const idx = store.artists.findIndex((a: any) => a.id === targetId || a.id === `art-${targetId}`);
    if (idx >= 0) {
      store.artists[idx] = {
        ...store.artists[idx],
        ...req.body,
        status: status || store.artists[idx].status,
        registrationRejectionReason: registrationRejectionReason !== undefined ? registrationRejectionReason : store.artists[idx].registrationRejectionReason
      };
      if (deleteSongs) {
        store.musics = store.musics.filter((m: any) => m.artistId !== targetId);
      }
      saveStore(store);
      return res.json({ success: true, message: 'Atis mete ajou avèk siksè', artist: store.artists[idx] });
    }
    return res.status(404).json({ success: false, message: 'Atis pa jwenn' });
  }

  if (req.method === 'DELETE') {
    const artistId = (req.query.id || req.body?.id || req.body?.artistId) as string;
    const deleteSongs = req.query.deleteSongs === 'true' || req.body?.deleteSongs === true;
    if (!artistId) {
      return res.status(400).json({ success: false, message: 'ID atis obligatwa' });
    }

    const initialLen = store.artists.length;
    store.artists = store.artists.filter((a: any) => a.id !== artistId && a.id !== `art-${artistId}`);
    if (deleteSongs) {
      store.musics = store.musics.filter((m: any) => m.artistId !== artistId);
    }
    saveStore(store);
    return res.json({
      success: true,
      message: `Atis la efase (${initialLen - store.artists.length} retire)`
    });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 4. MUSICS API (FULL CRUD & INCREMENT PLAY)
// ==========================================
app.all(['/backend/api/musics.php', '/api/musics'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    const { action, id, artistId, category } = req.query;
    
    // Quick handle for ?action=listen&id=...
    if (action === 'listen' && id) {
      const song = store.musics.find((m: any) => m.id === id);
      if (song) {
        song.listens = (Number(song.listens) || 0) + 1;
        saveStore(store);
        return res.json({ success: true, listens: song.listens });
      }
      return res.json({ success: true });
    }

    let list = [...store.musics];
    if (artistId) {
      list = list.filter((m: any) => m.artistId === artistId);
    }
    if (category && category !== 'Tout') {
      list = list.filter((m: any) => m.category === category);
    }
    return res.json({ success: true, data: { musics: list, count: list.length }, musics: list });
  }

  if (req.method === 'POST') {
    const data = req.body;
    
    // Handle action: 'play' or 'listen'
    if (data.action === 'play' || data.action === 'listen') {
      const targetId = data.musicId || data.id;
      const song = store.musics.find((m: any) => m.id === targetId);
      if (song) {
        song.listens = (Number(song.listens) || 0) + 1;
        saveStore(store);
        return res.json({ success: true, listens: song.listens });
      }
      return res.json({ success: true });
    }

    if (!data.title) {
      return res.status(400).json({ success: false, message: 'Tit mizik la obligatwa.' });
    }

    const item = { ...data, id: data.id || `m_${Date.now()}` };
    const existingIdx = store.musics.findIndex((m: any) => m.id === item.id);
    
    if (existingIdx >= 0) {
      // Update in place!
      store.musics[existingIdx] = { ...store.musics[existingIdx], ...item };
    } else {
      store.musics.unshift(item);
    }

    saveStore(store);
    return res.status(201).json({ success: true, musicId: item.id, music: item });
  }

  if (req.method === 'PUT') {
    const data = req.body;
    const targetId = data.id || data.musicId;
    if (data.action === 'listen' || data.action === 'play') {
      const song = store.musics.find((m: any) => m.id === targetId);
      if (song) {
        song.listens = (Number(song.listens) || 0) + 1;
        saveStore(store);
        return res.json({ success: true, listens: song.listens });
      }
    }
    if (targetId) {
      const idx = store.musics.findIndex((m: any) => m.id === targetId);
      if (idx >= 0) {
        store.musics[idx] = { ...store.musics[idx], ...data };
        saveStore(store);
        return res.json({ success: true, music: store.musics[idx] });
      }
    }
    return res.json({ success: true });
  }

  if (req.method === 'DELETE') {
    const musicId = (req.query.id || req.body?.id || req.body?.musicId) as string;
    if (musicId) {
      store.musics = store.musics.filter((m: any) => m.id !== musicId);
      saveStore(store);
      return res.json({ success: true, message: 'Mizik la efase nan backend' });
    }
    return res.status(400).json({ success: false, message: 'ID mizik obligatwa' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 5. DONATIONS API (FULL VALIDATION & STATS)
// ==========================================
app.all(['/backend/api/donations.php', '/api/donations'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    const { artistId, status } = req.query;
    let list = [...store.donations];
    if (artistId) {
      list = list.filter((d: any) => d.artistId === artistId);
    }
    if (status) {
      list = list.filter((d: any) => d.status === status);
    }
    return res.json({ success: true, data: { donations: list }, donations: list });
  }

  if (req.method === 'POST') {
    const item = { ...req.body, id: req.body.id || `don_${Date.now()}` };
    const existingIdx = store.donations.findIndex((d: any) => d.id === item.id);
    if (existingIdx >= 0) {
      store.donations[existingIdx] = { ...store.donations[existingIdx], ...item };
    } else {
      store.donations.unshift(item);
    }
    saveStore(store);
    return res.json({ success: true, donationId: item.id });
  }

  if (req.method === 'PUT') {
    const { id, status, accept } = req.body;
    const targetId = id || req.query.id;
    const targetStatus = status || (accept ? 'approved' : 'rejected');
    const idx = store.donations.findIndex((d: any) => d.id === targetId);
    if (idx >= 0) {
      store.donations[idx] = {
        ...store.donations[idx],
        ...req.body,
        status: targetStatus
      };
      if (targetStatus === 'approved') {
        const artIdx = store.artists.findIndex((a: any) => a.id === store.donations[idx].artistId);
        if (artIdx >= 0) {
          const amt = Number(store.donations[idx].amount) || 0;
          store.artists[artIdx].totalDonationsReceived = (Number(store.artists[artIdx].totalDonationsReceived) || 0) + amt;
        }
      }
      saveStore(store);
      return res.json({ success: true, donation: store.donations[idx] });
    }
    return res.status(404).json({ success: false, message: 'Donasyon pa jwenn' });
  }

  if (req.method === 'DELETE') {
    const id = req.query.id || req.body?.id;
    if (id) {
      store.donations = store.donations.filter((d: any) => d.id !== id);
      saveStore(store);
      return res.json({ success: true, message: 'Donasyon efase' });
    }
    return res.status(400).json({ success: false, message: 'ID obligatwa' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 6. RPA API (RIBRIK POUSE ATIS)
// ==========================================
app.all(['/backend/api/rpa.php', '/api/rpa'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    return res.json({ success: true, data: { rpa: store.rpa }, rpa: store.rpa });
  }

  if (req.method === 'POST') {
    if (Array.isArray(req.body)) {
      store.rpa = req.body;
    } else if (req.body && req.body.rpa && Array.isArray(req.body.rpa)) {
      store.rpa = req.body.rpa;
    } else if (req.body) {
      const item = { ...req.body, id: req.body.id || `rpa_${Date.now()}` };
      const idx = store.rpa.findIndex((r: any) => r.id === item.id);
      if (idx >= 0) {
        store.rpa[idx] = item;
      } else {
        store.rpa.unshift(item);
      }
    }
    saveStore(store);
    return res.json({ success: true, rpa: store.rpa });
  }

  if (req.method === 'DELETE') {
    const id = req.query.id || req.body?.id;
    if (id) {
      store.rpa = store.rpa.filter((r: any) => r.id !== id);
      saveStore(store);
      return res.json({ success: true, message: 'RPA efase' });
    }
    return res.status(400).json({ success: false, message: 'ID obligatwa' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 7. PUBS / ADS API
// ==========================================
app.all(['/backend/api/pubs.php', '/api/pubs'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    return res.json({ success: true, data: { pubs: store.pubs }, pubs: store.pubs });
  }

  if (req.method === 'POST') {
    if (Array.isArray(req.body)) {
      store.pubs = req.body;
    } else if (req.body && req.body.pubs && Array.isArray(req.body.pubs)) {
      store.pubs = req.body.pubs;
    } else if (req.body) {
      const item = { ...req.body, id: req.body.id || `pub_${Date.now()}` };
      const idx = store.pubs.findIndex((p: any) => p.id === item.id);
      if (idx >= 0) {
        store.pubs[idx] = item;
      } else {
        store.pubs.unshift(item);
      }
    }
    saveStore(store);
    return res.json({ success: true, pubs: store.pubs });
  }

  if (req.method === 'DELETE') {
    const id = req.query.id || req.body?.id;
    if (id) {
      store.pubs = store.pubs.filter((p: any) => p.id !== id);
      saveStore(store);
      return res.json({ success: true, message: 'Pub efase' });
    }
    return res.status(400).json({ success: false, message: 'ID obligatwa' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 8. SOCIAL POSTS API
// ==========================================
app.all(['/backend/api/social.php', '/api/social'], (req, res) => {
  const store = getStore();

  if (req.method === 'GET') {
    return res.json({ success: true, data: { posts: store.socialPosts }, posts: store.socialPosts });
  }

  if (req.method === 'POST') {
    const item = { ...req.body, id: req.body.id || `post_${Date.now()}` };
    const idx = store.socialPosts.findIndex((p: any) => p.id === item.id);
    if (idx >= 0) {
      store.socialPosts[idx] = item;
    } else {
      store.socialPosts.unshift(item);
    }
    saveStore(store);
    return res.json({ success: true, post: item });
  }

  if (req.method === 'DELETE') {
    const id = req.query.id || req.body?.id;
    if (id) {
      store.socialPosts = store.socialPosts.filter((p: any) => p.id !== id);
      saveStore(store);
      return res.json({ success: true, message: 'Pòs efase' });
    }
    return res.status(400).json({ success: false, message: 'ID obligatwa' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 9. BULK SYNC API (HOSTINGER & MYSQL SYNC)
// ==========================================
app.all(['/backend/api/sync.php', '/api/sync'], (req, res) => {
  const store = getStore();

  if (req.method === 'POST') {
    const { artists, musics, pubs, rpa, settings, donations } = req.body;
    if (Array.isArray(artists)) store.artists = artists;
    if (Array.isArray(musics)) store.musics = musics;
    if (Array.isArray(pubs)) store.pubs = pubs;
    if (Array.isArray(rpa)) store.rpa = rpa;
    if (Array.isArray(donations)) store.donations = donations;
    if (settings) store.settings = { ...store.settings, ...settings };

    saveStore(store);
    return res.json({ success: true, message: 'Senkronizasyon backend reyisi' });
  }

  return res.json({ success: true, store });
});

// ==========================================
// 10. PAYMENT SETTINGS API
// ==========================================
app.all(['/backend/api/settings.php', '/api/settings'], (req, res) => {
  const store = getStore();
  if (req.method === 'GET') {
    return res.json({ success: true, settings: store.settings });
  }
  if (req.method === 'POST' || req.method === 'PUT') {
    if (req.body.settings) {
      store.settings = { ...store.settings, ...req.body.settings };
    } else if (req.body) {
      store.settings = { ...store.settings, ...req.body };
    }
    saveStore(store);
    return res.json({ success: true, settings: store.settings });
  }
  return res.json({ success: true });
});

// ==========================================
// 5. VITE DEV SERVER OR STATIC PROD
// ==========================================
async function startServer() {
  const server = http.createServer(app);

  if (process.env.NODE_ENV === 'production' && fs.existsSync(path.join(__dirname, 'dist'))) {
    app.use(express.static(path.join(__dirname, 'dist')));
    app.get('*', (req, res) => {
      res.sendFile(path.join(__dirname, 'dist', 'index.html'));
    });
  } else {
    const { createServer } = await import('vite');
    const vite = await createServer({
      server: {
        middlewareMode: true,
        hmr: { server }
      },
      appType: 'spa'
    });
    app.use(vite.middlewares);
  }

  server.listen(Number(PORT), '0.0.0.0', () => {
    console.log(`[UpMizik Server] Running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
