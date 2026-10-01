import { MusicItem, ArtistUser, DonationItem, PubItem, RpaItem, ArchiveRecord, CommentItem, SocialPost, SocialPostComment, ArtistInboxMessage } from '../types';

export const INITIAL_ARTISTS: ArtistUser[] = [
  {
    id: 'art_test_peterson_509',
    name: 'Jean-Pierre Peterson',
    stageName: 'Ti Peterson Mizik',
    email: 'peterson.test@upmizik.com',
    phone: '509 3788-1234',
    city: 'Pòtoprens',
    pin: '1234',
    avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&auto=format&fit=crop&q=80',
    bio: 'Nouvo chantè ak konpozitè k ap evolye nan vil Pòtoprens, espesyalize nan Konpa modèn, Afrobeats kreyòl ak R&B. Mwen voye prèv peman $4.99 USD (650 HTG) mwen pou validasyon ofisyèl.',
    musicalRoots: 'Konpa Dirèk, Rasin, Afrobeats',
    musicalInfluences: 'Harmonik, Klass, Kai, Rutshelle Guillaume',
    artisticVision: 'Mete kilti ayisyen sou tout platfòm mondyal yo atravè bon jan kalite pwodiksyon mizikal.',
    artistQuote: 'Mizik se nanm mwen, pasyon m se pataje lanmou ak pèp ayisyen an.',
    status: 'pending',
    registrationProofUrl: '/backend/uploads/proofs/prev_moncash_test_atis.svg',
    registrationFeeUsd: 4.99,
    registrationFeeHtg: 650,
    registrationDate: '2026-10-01',
    totalListens: 0,
    totalDonationsReceived: 0,
    youtubeUrl: 'https://youtube.com/@tipetersonmizik',
    instagramUrl: 'https://instagram.com/tipetersonmizik'
  }
];

export const INITIAL_MUSIC: MusicItem[] = [];

export const INITIAL_DONATIONS: DonationItem[] = [];

export const INITIAL_PUBS: PubItem[] = [];

export const INITIAL_RPA: RpaItem[] = [];

export const INITIAL_ARCHIVES: ArchiveRecord[] = [];

export const INITIAL_COMMENTS: CommentItem[] = [];

export const INITIAL_SOCIAL_POSTS: SocialPost[] = [];

export const INITIAL_SOCIAL_POST_COMMENTS: Record<string, SocialPostComment[]> = {};

export const INITIAL_ARTIST_INBOX: ArtistInboxMessage[] = [];
