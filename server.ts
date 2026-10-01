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

// Serve uploads statically
app.use('/backend/uploads', express.static(UPLOADS_DIR));
app.use('/uploads', express.static(UPLOADS_DIR));

// Helper: Read/Write Store
interface AppStore {
  artists: any[];
  musics: any[];
  donations: any[];
  settings: any;
}

function getStore(): AppStore {
  try {
    if (fs.existsSync(STORE_FILE)) {
      const content = fs.readFileSync(STORE_FILE, 'utf-8');
      return JSON.parse(content);
    }
  } catch (err) {
    console.error('Error reading store file:', err);
  }
  return {
    artists: [],
    musics: [],
    donations: [],
    settings: {
      artistRegistrationFeeUsd: 4.99,
      artistRegistrationFeeHtg: 650,
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
    const { id, status, registrationRejectionReason } = req.body;
    if (!id) {
      return res.status(400).json({ success: false, message: 'ID obligatwa' });
    }

    const idx = store.artists.findIndex((a: any) => a.id === id);
    if (idx >= 0) {
      store.artists[idx] = {
        ...store.artists[idx],
        ...req.body,
        status: status || store.artists[idx].status,
        registrationRejectionReason: registrationRejectionReason ?? store.artists[idx].registrationRejectionReason
      };
      saveStore(store);
      return res.json({ success: true, message: 'Atis mete ajou avèk siksè' });
    }
    return res.status(404).json({ success: false, message: 'Atis pa jwenn' });
  }

  return res.status(405).json({ success: false, message: 'Metòd pa sipòte' });
});

// ==========================================
// 4. MUSIC, DONATIONS, SETTINGS API
// ==========================================
app.all(['/backend/api/musics.php', '/api/musics'], (req, res) => {
  const store = getStore();
  if (req.method === 'GET') {
    return res.json({ success: true, data: { musics: store.musics }, musics: store.musics });
  }
  if (req.method === 'POST') {
    const item = { ...req.body, id: req.body.id || `m_${Date.now()}` };
    store.musics.unshift(item);
    saveStore(store);
    return res.json({ success: true, musicId: item.id });
  }
  return res.json({ success: true });
});

app.all(['/backend/api/donations.php', '/api/donations'], (req, res) => {
  const store = getStore();
  if (req.method === 'GET') {
    return res.json({ success: true, data: { donations: store.donations }, donations: store.donations });
  }
  if (req.method === 'POST') {
    const item = { ...req.body, id: req.body.id || `don_${Date.now()}` };
    store.donations.unshift(item);
    saveStore(store);
    return res.json({ success: true, donationId: item.id });
  }
  return res.json({ success: true });
});

app.all(['/backend/api/settings.php', '/api/settings'], (req, res) => {
  const store = getStore();
  if (req.method === 'GET') {
    return res.json({ success: true, settings: store.settings });
  }
  if (req.method === 'POST') {
    if (req.body.settings) {
      store.settings = req.body.settings;
      saveStore(store);
    }
    return res.json({ success: true });
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
