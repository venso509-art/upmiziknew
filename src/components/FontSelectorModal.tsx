import React, { useState } from 'react';
import { Sparkles, Check, ChevronRight, X, Music } from 'lucide-react';

interface FontOption {
  id: number;
  name: string;
  category: string;
  titleFont: string;
  bodyFont: string;
  fontFamilyCSS: string;
  description: string;
  badge: string;
  gradient: string;
}

export const FONT_OPTIONS: FontOption[] = [
  {
    id: 1,
    name: 'Modern Streaming (Spotify & Apple Music)',
    category: 'Pro & Tech',
    titleFont: "'Plus Jakarta Sans', sans-serif",
    bodyFont: "'Inter', sans-serif",
    fontFamilyCSS: "'Plus Jakarta Sans', 'Inter', sans-serif",
    description: 'Polis ki trè nèt, fonse, modèn ak gwo klète sou tout kalite ekran.',
    badge: 'Popilè',
    gradient: 'from-amber-400 to-red-500'
  },
  {
    id: 2,
    name: 'Futuris & Urban (Afrobeats & Rap Kreyòl)',
    category: 'Enèjik & Urban',
    titleFont: "'Syne', sans-serif",
    bodyFont: "'DM Sans', sans-serif",
    fontFamilyCSS: "'Syne', 'DM Sans', sans-serif",
    description: 'Bèl karaktè dinamik, tandans jèn, pafè pou mizik k ap bouyi.',
    badge: 'Tandans',
    gradient: 'from-purple-400 to-pink-500'
  },
  {
    id: 3,
    name: 'Elegans Deluxe (Konpa VIP & Afropop)',
    category: 'Liks & Dous',
    titleFont: "'Outfit', sans-serif",
    bodyFont: "'Plus Jakarta Sans', sans-serif",
    fontFamilyCSS: "'Outfit', 'Plus Jakarta Sans', sans-serif",
    description: 'Koub jewometrik dous, bèl lèt elegant ki bay sit la yon aparans entènasyonal.',
    badge: 'Liks',
    gradient: 'from-emerald-400 to-teal-500'
  },
  {
    id: 4,
    name: 'Gwo Enpak Display (Festival & Billboard)',
    category: 'Gwo Enpak',
    titleFont: "'Unbounded', sans-serif",
    bodyFont: "'DM Sans', sans-serif",
    fontFamilyCSS: "'Unbounded', 'DM Sans', sans-serif",
    description: 'Lèt fonse ak espasaj elaji ki atire atansyon imedyatman sou non atis yo.',
    badge: 'Enpak Fò',
    gradient: 'from-blue-400 to-indigo-500'
  },
  {
    id: 5,
    name: 'Klasik Épuré (Poppins Minimalist)',
    category: 'Épuré & Klè',
    titleFont: "'Poppins', sans-serif",
    bodyFont: "'Poppins', sans-serif",
    fontFamilyCSS: "'Poppins', sans-serif",
    description: 'Fòm won, dous, trè lizib e amikal pou koute mizik pandan plizyè èdtan.',
    badge: 'Konfò Lekti',
    gradient: 'from-cyan-400 to-blue-500'
  },
  {
    id: 6,
    name: 'Afro-Kreyòl & Ritmik (Bricolage Grotesque)',
    category: 'Kiltirèl & Ritm',
    titleFont: "'Bricolage Grotesque', sans-serif",
    bodyFont: "'Manrope', sans-serif",
    fontFamilyCSS: "'Bricolage Grotesque', 'Manrope', sans-serif",
    description: 'Ritm natif natal, stil kiltirèl ekspresif ak yon sansasyon atistik inik.',
    badge: 'Natif Natal',
    gradient: 'from-rose-400 to-amber-500'
  }
];

interface FontSelectorModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelectFont: (font: FontOption) => void;
  currentFontId: number;
}

export const FontSelectorModal: React.FC<FontSelectorModalProps> = ({
  isOpen,
  onClose,
  onSelectFont,
  currentFontId
}) => {
  const [selectedId, setSelectedId] = useState(currentFontId);

  if (!isOpen) return null;

  const handleApply = (option: FontOption) => {
    setSelectedId(option.id);
    onSelectFont(option);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
      <div className="relative w-full max-w-4xl max-h-[90vh] bg-[#0c1017] border border-white/15 rounded-3xl p-6 sm:p-8 overflow-y-auto shadow-2xl">
        {/* Header */}
        <div className="flex items-start justify-between pb-6 border-b border-white/10">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-semibold mb-2">
              <Sparkles className="w-3.5 h-3.5" />
              <span>Chwazi Stil Ekriti (Typography)</span>
            </div>
            <h2 className="text-2xl sm:text-3xl font-black text-white tracking-tight">
              Kijan w vle tèks <span className="text-amber-400">UpMizik</span> la parèt?
            </h2>
            <p className="text-slate-400 text-sm mt-1">
              Klike sou nimewo w pi renmen an pou w aplike l dirèkteman sou tout sit la.
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* 6 Visual Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
          {FONT_OPTIONS.map((opt) => {
            const isSelected = selectedId === opt.id;
            return (
              <div
                key={opt.id}
                onClick={() => handleApply(opt)}
                className={`group relative p-5 rounded-2xl cursor-pointer border transition-all duration-300 ${
                  isSelected
                    ? 'bg-gradient-to-br from-amber-500/15 via-[#161c28] to-[#0d121c] border-amber-500 shadow-lg shadow-amber-500/10'
                    : 'bg-[#121722]/80 hover:bg-[#181f2d] border-white/10 hover:border-white/20'
                }`}
              >
                {/* Number Badge */}
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <span className="flex items-center justify-center w-8 h-8 rounded-xl bg-amber-500 text-black font-black text-sm shadow-md">
                      #{opt.id}
                    </span>
                    <span className="text-xs font-semibold text-slate-300">
                      {opt.category}
                    </span>
                  </div>
                  <span className="text-[11px] px-2.5 py-0.5 rounded-full bg-white/10 text-slate-300 border border-white/10 font-medium">
                    {opt.badge}
                  </span>
                </div>

                {/* Big Visual Text Showcase */}
                <div className="my-3 p-4 rounded-xl bg-[#070a0f] border border-white/5">
                  <div
                    style={{ fontFamily: opt.titleFont }}
                    className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-none"
                  >
                    UpMizik <span className="text-amber-400 text-lg sm:text-xl font-bold">509</span>
                  </div>
                  <div
                    style={{ fontFamily: opt.bodyFont }}
                    className="text-xs sm:text-sm text-slate-400 mt-2 flex items-center gap-2 font-medium"
                  >
                    <Music className="w-3.5 h-3.5 text-amber-400" />
                    <span>Difizyon Mizik Ayisyen & Sipò Dirèk Pou Atis</span>
                  </div>
                </div>

                {/* Description & Action */}
                <div className="flex items-center justify-between mt-3 pt-2 border-t border-white/5 text-xs">
                  <span className="text-slate-400">{opt.name}</span>
                  <div className="flex items-center gap-1.5 font-bold">
                    {isSelected ? (
                      <span className="text-amber-400 flex items-center gap-1">
                        <Check className="w-4 h-4" /> Chwazi
                      </span>
                    ) : (
                      <span className="text-slate-400 group-hover:text-white flex items-center gap-1">
                        Klike pou aplike <ChevronRight className="w-3.5 h-3.5" />
                      </span>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-white/10">
          <button
            onClick={onClose}
            className="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-sm transition-all shadow-lg shadow-amber-950/40"
          >
            Fèmen & Sove
          </button>
        </div>
      </div>
    </div>
  );
};
