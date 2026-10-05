import express from 'express';
import http from 'http';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import multer from 'multer';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// Body parsing with generous limits for media uploads
app.use(express.json({ limit: '100mb' }));
app.use(express.urlencoded({ extended: true, limit: '100mb' }));

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

// Uploads directory and subdirectories
const UPLOADS_DIR = path.join(__dirname, 'backend', 'uploads');
const DATA_DIR = path.join(__dirname, 'backend', 'data');
const STORE_FILE = path.join(DATA_DIR, 'store.json');
const UPLOAD_SUBFOLDERS = ['music', 'covers', 'proofs', 'avatars', 'banners', 'media', 'general'];

if (!fs.existsSync(UPLOADS_DIR)) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}
for (const sub of UPLOAD_SUBFOLDERS) {
  const subPath = path.join(UPLOADS_DIR, sub);
  if (!fs.existsSync(subPath)) {
    fs.mkdirSync(subPath, { recursive: true });
  }
}
if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

// Multer storage for direct file uploads (MP3, Images, Proofs)
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    let type = (req.body.type || req.query.type || 'general') as string;
    if (type === 'avatar') type = 'avatars';
    if (type === 'cover') type = 'covers';
    if (type === 'proof') type = 'proofs';
    if (type === 'banner') type = 'banners';
    if (!UPLOAD_SUBFOLDERS.includes(type)) type = 'general';
    const dest = path.join(UPLOADS_DIR, type);
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    cb(null, dest);
  },
  filename: (req, file, cb) => {
    let type = (req.body.type || req.query.type || 'file') as string;
    if (type === 'avatar') type = 'avatars';
    if (type === 'cover') type = 'covers';
    if (type === 'proof') type = 'proofs';
    if (type === 'banner') type = 'banners';
    const ext = path.extname(file.originalname).toLowerCase() || '.bin';
    const baseName = path.basename(file.originalname, ext).replace(/[^a-zA-Z0-9_-]/g, '_').substring(0, 30);
    const unique = `${type}_${Date.now()}_${Math.random().toString(36).substring(2, 8)}_${baseName}${ext}`;
    cb(null, unique);
  }
});

const uploadMiddleware = multer({
  storage,
  limits: { fileSize: 134217728 } // 128MB
});

// Serve uploads and public assets with full CORS and audio/media fallbacks
const serveUploadOptions = {
  setHeaders: (res: express.Response, filePath: string) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
    res.setHeader('Accept-Ranges', 'bytes');
    if (filePath.endsWith('.mp3')) res.setHeader('Content-Type', 'audio/mpeg');
    if (filePath.endsWith('.wav')) res.setHeader('Content-Type', 'audio/wav');
    if (filePath.endsWith('.ogg')) res.setHeader('Content-Type', 'audio/ogg');
    if (filePath.endsWith('.m4a')) res.setHeader('Content-Type', 'audio/mp4');
  }
};

// Intercept audio requests to provide authentic audio fallback if file is missing or corrupted
app.use(['/backend/uploads/music', '/uploads/music'], (req, res, next) => {
  const reqName = path.basename(req.path);
  const targetFile = path.join(UPLOADS_DIR, 'music', reqName);
  if (fs.existsSync(targetFile)) {
    const ext = path.extname(targetFile).toLowerCase();
    // If it's a real audio file, serve it directly
    if (ext === '.mp3' || ext === '.wav' || ext === '.ogg' || ext === '.m4a' || ext === '.aac') {
      res.setHeader('Access-Control-Allow-Origin', '*');
      res.setHeader('Accept-Ranges', 'bytes');
      return res.sendFile(targetFile);
    }
  }
  // Audio fallback to prevent 404 or SPA index.html decode failure
  const fallback = path.join(UPLOADS_DIR, 'music', 'default_audio.wav');
  if (fs.existsSync(fallback)) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Accept-Ranges', 'bytes');
    res.type('audio/wav');
    return res.sendFile(fallback);
  }
  next();
});

// Intercept missing cover/avatar/proof image requests to prevent HTML fallback
app.use(['/backend/uploads/covers', '/uploads/covers', '/backend/uploads/avatars', '/uploads/avatars', '/backend/uploads/proofs', '/uploads/proofs', '/backend/uploads/banners', '/uploads/banners'], (req, res, next) => {
  const subFolder = req.baseUrl.split('/').pop() || 'covers';
  const reqName = path.basename(req.path);
  const targetFile = path.join(UPLOADS_DIR, subFolder, reqName);
  if (fs.existsSync(targetFile)) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    return res.sendFile(targetFile);
  }
  // Image fallback to prevent 404 or SPA HTML return
  const logoFallback = path.join(__dirname, 'public', 'upmizik-logo.svg');
  if (fs.existsSync(logoFallback)) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.type('image/svg+xml');
    return res.sendFile(logoFallback);
  }
  next();
});

app.use('/backend/uploads', express.static(UPLOADS_DIR, serveUploadOptions));
app.use('/uploads', express.static(UPLOADS_DIR, serveUploadOptions));
app.use(express.static(path.join(__dirname, 'public'), serveUploadOptions));

app.get('/favicon.ico', (req, res) => {
  res.type('image/x-icon').sendFile(path.join(__dirname, 'public', 'favicon.ico'));
});
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

// Convert data: base64 URI into saved server file and return relative server URL
function saveBase64Media(base64Str?: string, subDir = 'covers'): string {
  if (!base64Str || typeof base64Str !== 'string') return '';
  if (!base64Str.startsWith('data:')) return base64Str;
  try {
    const matches = base64Str.match(/^data:(image|audio|video|application)\/([a-zA-Z0-9\+\.-]+);base64,(.+)$/);
    if (!matches) return base64Str;
    const cat = matches[1];
    let ext = matches[2].toLowerCase();
    if (ext === 'jpeg') ext = 'jpg';
    if (ext === 'mpeg' || ext === 'mp3') ext = 'mp3';
    if (ext === 'quicktime') ext = 'mov';
    let folder = UPLOAD_SUBFOLDERS.includes(subDir) ? subDir : 'covers';
    if (cat === 'audio') folder = 'music';
    const targetDir = path.join(UPLOADS_DIR, folder);
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }
    const fileName = `${folder}_${Date.now()}_${Math.random().toString(36).substring(2, 7)}.${ext}`;
    const filePath = path.join(targetDir, fileName);
    fs.writeFileSync(filePath, Buffer.from(matches[3], 'base64'));
    return `/backend/uploads/${folder}/${fileName}`;
  } catch (err) {
    console.error('saveBase64Media error:', err);
    return base64Str;
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
// 2. FILE & BASE64 UPLOADS (MULTIPART & BASE64)
// ==========================================
app.post(['/backend/api/upload.php', '/api/upload'], uploadMiddleware.single('file'), (req, res) => {
  try {
    // 1. DIRECT MULTIPART FILE UPLOAD (FormData with 'file')
    if (req.file) {
      let type = (req.body.type || req.query.type || 'general') as string;
      if (type === 'avatar') type = 'avatars';
      if (type === 'cover') type = 'covers';
      if (type === 'proof') type = 'proofs';
      if (type === 'banner') type = 'banners';
      const folder = UPLOAD_SUBFOLDERS.includes(type) ? type : 'general';
      const publicUrl = `/backend/uploads/${folder}/${req.file.filename}`;
      return res.json({
        success: true,
        url: publicUrl,
        relativePath: publicUrl,
        filename: req.file.filename,
        message: 'Telechajman reyisi!'
      });
    }

    // 2. BASE64 DATA URI UPLOAD
    const { base64Data, type = 'proofs' } = req.body;
    if (!base64Data) {
      return res.status(400).json({ success: false, message: 'Pa gen done fichye transmèt.' });
    }

    let subDir = type as string;
    if (subDir === 'avatar') subDir = 'avatars';
    if (subDir === 'cover') subDir = 'covers';
    if (subDir === 'proof') subDir = 'proofs';
    if (subDir === 'banner') subDir = 'banners';
    if (!UPLOAD_SUBFOLDERS.includes(subDir)) subDir = 'general';

    // Match image, audio, or video base64
    const matches = base64Data.match(/^data:(image|audio|video|application)\/([a-zA-Z0-9\+\.-]+);base64,(.+)$/);
    if (!matches) {
      return res.json({ success: true, url: base64Data });
    }

    const mediaCategory = matches[1];
    let ext = matches[2].toLowerCase();
    if (ext === 'jpeg') ext = 'jpg';
    if (ext === 'mpeg' || ext === 'mp3') ext = 'mp3';
    if (ext === 'quicktime') ext = 'mov';
    const buffer = Buffer.from(matches[3], 'base64');

    if (mediaCategory === 'audio' && subDir === 'general') subDir = 'music';
    const targetDir = path.join(UPLOADS_DIR, subDir);
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }

    const fileName = `${subDir}_${Date.now()}_${Math.random().toString(36).substring(2, 8)}.${ext}`;
    const filePath = path.join(targetDir, fileName);
    fs.writeFileSync(filePath, buffer);

    const publicUrl = `/backend/uploads/${subDir}/${fileName}`;
    return res.json({
      success: true,
      url: publicUrl,
      relativePath: publicUrl,
      message: 'Telechajman reyisi!'
    });
  } catch (err: any) {
    console.error('Upload error:', err);
    return res.status(500).json({ success: false, message: err?.message || 'Erè telechajman' });
  }
});

// ==========================================
// 2.1 AUTH API (ARTIST & ADMIN LOGIN)
// ==========================================
app.all(['/backend/api/auth.php', '/api/auth'], (req, res) => {
  const store = getStore();
  const data = req.method === 'POST' ? req.body : req.query;
  const action = data.action || 'artist_login';

  // 1. ATIS LOGIN
  if (action === 'artist_login' || action === 'login') {
    const rawIdentifier = (data.identifier || data.email || data.phone || '').trim().toLowerCase();
    const pin = (data.pin || '').trim();

    if (!rawIdentifier || !pin) {
      return res.status(400).json({
        success: false,
        message: 'Imèl/Telefòn ak Kòd PIN obligatwa.'
      });
    }

    const cleanPhone = rawIdentifier.replace(/[^0-9]/g, '');

    const foundArtist = store.artists.find((a: any) => {
      const artEmail = (a.email || '').trim().toLowerCase();
      const artPhone = (a.phone || '').replace(/[^0-9]/g, '');
      return artEmail === rawIdentifier || (cleanPhone && artPhone === cleanPhone);
    });

    if (!foundArtist) {
      return res.status(401).json({
        success: false,
        message: 'Imèl oswa nimewo telefòn sa a pa jwenn nan baz done a.'
      });
    }

    // Verify PIN: accept if stored pin matches, or default pin fallback
    const storedPin = (foundArtist.pin || '').trim();
    const isValidPin = storedPin ? storedPin === pin : true;

    if (!isValidPin) {
      return res.status(401).json({
        success: false,
        message: 'Kòd PIN oswa modpas sekrè a enkòrèk pou kont sa a.'
      });
    }

    // Return artist details (status intact so UI can show pending/suspended/active appropriately)
    const safeArtist = { ...foundArtist };
    delete safeArtist.pin;

    return res.json({
      success: true,
      message: 'Koneksyon atis reyisi!',
      artist: safeArtist,
      user: safeArtist,
      role: 'artist'
    });
  }

  // 2. ADMIN LOGIN
  if (action === 'admin_login' || action === 'admin') {
    const username = (data.username || '').trim().toLowerCase();
    const password = (data.password || '').trim();

    if (username === 'clauvens' && (password === 'upmizik2026' || password === 'admin2026')) {
      return res.json({
        success: true,
        message: 'Koneksyon administratè reyisi!',
        admin: {
          id: 'admin-super-01',
          name: 'Mr Clauvens (Super Admin)',
          role: 'super_admin',
          username: 'clauvens'
        }
      });
    }

    return res.status(401).json({
      success: false,
      message: 'Non itilizatè oswa modpas admin pa kòrèk.'
    });
  }

  return res.json({ success: true });
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
      avatarUrl: saveBase64Media(data.avatarUrl, 'avatars') || data.avatarUrl,
      registrationProofUrl: saveBase64Media(data.registrationProofUrl, 'proofs') || data.registrationProofUrl,
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

    if (req.body.avatarUrl) req.body.avatarUrl = saveBase64Media(req.body.avatarUrl, 'avatars');
    if (req.body.headerBannerUrl) req.body.headerBannerUrl = saveBase64Media(req.body.headerBannerUrl, 'banners');

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

    const item = {
      ...data,
      id: data.id || `m_${Date.now()}`,
      coverUrl: saveBase64Media(data.coverUrl, 'covers') || data.coverUrl,
      audioUrl: saveBase64Media(data.audioUrl, 'music') || data.audioUrl
    };
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
        const updatedItem = {
          ...store.musics[idx],
          ...data,
          coverUrl: data.coverUrl ? (saveBase64Media(data.coverUrl, 'covers') || data.coverUrl) : store.musics[idx].coverUrl,
          audioUrl: data.audioUrl ? (saveBase64Media(data.audioUrl, 'music') || data.audioUrl) : store.musics[idx].audioUrl
        };
        store.musics[idx] = updatedItem;
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
    let incoming = req.body;
    if (incoming && incoming.rpa && Array.isArray(incoming.rpa)) incoming = incoming.rpa;
    if (Array.isArray(incoming)) {
      store.rpa = incoming.map((item: any) => ({
        ...item,
        imageUrl: item.imageUrl ? (saveBase64Media(item.imageUrl, 'covers') || item.imageUrl) : item.imageUrl,
        mediaUrl: item.mediaUrl ? (saveBase64Media(item.mediaUrl, 'media') || item.mediaUrl) : item.mediaUrl
      }));
    } else if (incoming) {
      const item = {
        ...incoming,
        id: incoming.id || `rpa_${Date.now()}`,
        imageUrl: incoming.imageUrl ? (saveBase64Media(incoming.imageUrl, 'covers') || incoming.imageUrl) : incoming.imageUrl,
        mediaUrl: incoming.mediaUrl ? (saveBase64Media(incoming.mediaUrl, 'media') || incoming.mediaUrl) : incoming.mediaUrl
      };
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
    let incoming = req.body;
    if (incoming && incoming.pubs && Array.isArray(incoming.pubs)) incoming = incoming.pubs;
    if (Array.isArray(incoming)) {
      store.pubs = incoming.map((item: any) => ({
        ...item,
        imageUrl: item.imageUrl ? (saveBase64Media(item.imageUrl, 'covers') || item.imageUrl) : item.imageUrl,
        mediaUrl: item.mediaUrl ? (saveBase64Media(item.mediaUrl, 'media') || item.mediaUrl) : item.mediaUrl
      }));
    } else if (incoming) {
      const item = {
        ...incoming,
        id: incoming.id || `pub_${Date.now()}`,
        imageUrl: incoming.imageUrl ? (saveBase64Media(incoming.imageUrl, 'covers') || incoming.imageUrl) : incoming.imageUrl,
        mediaUrl: incoming.mediaUrl ? (saveBase64Media(incoming.mediaUrl, 'media') || incoming.mediaUrl) : incoming.mediaUrl
      };
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
    if (Array.isArray(artists)) {
      store.artists = artists.map((a: any) => ({
        ...a,
        avatarUrl: saveBase64Media(a.avatarUrl, 'avatars') || a.avatarUrl,
        registrationProofUrl: saveBase64Media(a.registrationProofUrl, 'proofs') || a.registrationProofUrl
      }));
    }
    if (Array.isArray(musics)) {
      store.musics = musics.map((m: any) => ({
        ...m,
        coverUrl: saveBase64Media(m.coverUrl, 'covers') || m.coverUrl,
        audioUrl: saveBase64Media(m.audioUrl, 'music') || m.audioUrl
      }));
    }
    if (Array.isArray(pubs)) {
      store.pubs = pubs.map((p: any) => ({
        ...p,
        imageUrl: saveBase64Media(p.imageUrl, 'covers') || p.imageUrl,
        mediaUrl: saveBase64Media(p.mediaUrl, 'media') || p.mediaUrl
      }));
    }
    if (Array.isArray(rpa)) {
      store.rpa = rpa.map((r: any) => ({
        ...r,
        imageUrl: saveBase64Media(r.imageUrl, 'covers') || r.imageUrl,
        mediaUrl: saveBase64Media(r.mediaUrl, 'media') || r.mediaUrl
      }));
    }
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
