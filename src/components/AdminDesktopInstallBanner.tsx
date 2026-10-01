import React, { useState } from 'react';
import { Monitor, Download, CheckCircle2, Sparkles, HelpCircle, X, ExternalLink } from 'lucide-react';
import { usePWAInstall } from '../hooks/usePWAInstall';

export const AdminDesktopInstallBanner: React.FC = () => {
  const { isInstallable, isInstalled, install } = usePWAInstall();
  const [showGuide, setShowGuide] = useState(false);
  const [isInstalling, setIsInstalling] = useState(false);

  const handleInstallClick = async () => {
    if (isInstallable) {
      setIsInstalling(true);
      await install();
      setIsInstalling(false);
    } else {
      setShowGuide(true);
    }
  };

  return (
    <>
      <div className="relative overflow-hidden bg-gradient-to-r from-[#17120a] via-[#111827] to-[#0a1813] border-2 border-amber-500/50 rounded-2xl p-3.5 sm:p-4 shadow-xl backdrop-blur-xl animate-fadeIn">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-yellow-500 text-slate-950 flex items-center justify-center shrink-0 shadow-lg shadow-amber-500/30">
              <Monitor className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="text-xs font-black text-white flex items-center gap-1.5">
                  <span><span className="text-red-500">Up</span><span className="text-blue-500">Mizik</span> Admin Desk (Lojisyèl PC / Mac)</span>
                  <span className="text-[9px] px-2 py-0.2 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30 font-mono">
                    Opsyon A • Standalone
                  </span>
                </span>
                {isInstalled && (
                  <span className="text-[10px] font-bold text-emerald-400 bg-emerald-500/20 px-2 py-0.5 rounded-full border border-emerald-500/30 flex items-center gap-1 font-mono">
                    <CheckCircle2 className="w-3 h-3" />
                    Enstale sou Machin
                  </span>
                )}
              </div>
              <p className="text-xs text-slate-300 mt-0.5">
                {isInstalled
                  ? 'Aplikasyon an ap fonksyone nan mòd lojisyèl natif sou machin ou avèk koneksyon an dirèk sou sèvè a.'
                  : 'Enstale panèl admin an kòm yon lojisyèl apa sou biwo PC/Mac ou pou w jere atis, prèv MonCash ak notifikasyon tout tan san pase pa navigatè.'}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
            {!isInstalled && (
              <button
                type="button"
                onClick={handleInstallClick}
                disabled={isInstalling}
                className="px-4 py-2 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-yellow-500 hover:from-amber-300 hover:to-yellow-400 text-slate-950 shadow-lg shadow-amber-500/30 flex items-center gap-2 transition-all active:scale-95 disabled:opacity-50"
              >
                <Download className="w-3.5 h-3.5" />
                <span>{isInstalling ? 'Enstalasyon...' : 'Enstale sou Machin ou'}</span>
              </button>
            )}

            <button
              type="button"
              onClick={() => setShowGuide(true)}
              className="p-2 rounded-xl text-slate-400 hover:text-white bg-white/[0.05] hover:bg-white/[0.1] border border-white/[0.08] transition-all"
              title="Kijan pou w enstale l sou Chrome / Edge / Windows / Mac"
            >
              <HelpCircle className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Guide Modal */}
      {showGuide && (
        <div className="fixed inset-0 z-[300] bg-black/80 backdrop-blur-md flex items-center justify-center p-4 animate-fadeIn">
          <div className="bg-[#0b1120] border-2 border-amber-500/50 rounded-3xl max-w-lg w-full p-6 shadow-2xl relative space-y-5">
            <button
              type="button"
              onClick={() => setShowGuide(false)}
              className="absolute top-4 right-4 p-2 text-slate-400 hover:text-white rounded-full bg-white/[0.05]"
            >
              <X className="w-5 h-5" />
            </button>

            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center">
                <Monitor className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-lg font-black text-white">Kijan pou w Enstale UpMizik Admin sou PC</h3>
                <p className="text-xs text-slate-400">Gid etap pa etap pou Windows, Mac oswa Linux</p>
              </div>
            </div>

            <div className="space-y-3 text-xs text-slate-200">
              <div className="bg-white/[0.03] border border-white/[0.08] rounded-2xl p-3.5 space-y-2">
                <span className="font-bold text-amber-300 flex items-center gap-1.5 text-sm">
                  <span>1. Sou Google Chrome oswa Microsoft Edge :</span>
                </span>
                <p className="text-slate-300 leading-relaxed">
                  Gade nan <strong>ba adrès la (toutotan a dwat)</strong> : w ap wè yon ti ikòn ekran avèk yon flèch ki desann (<Download className="w-3.5 h-3.5 inline text-amber-400" />) ki di <em>"Install UpMizik Admin Desk"</em> oswa <em>"Enstale aplikasyon"</em>.
                </p>
                <p className="text-slate-300 leading-relaxed">
                  Oswa klike sou <strong>3 ti pwen yo (Menu)</strong> anwo a dwat nan navigatè a &gt; chwazi <strong>"Enstale UpMizik Admin Desk"</strong> (oubyen <em>Save and share &gt; Install as app</em>).
                </p>
              </div>

              <div className="bg-white/[0.03] border border-white/[0.08] rounded-2xl p-3.5 space-y-2">
                <span className="font-bold text-amber-300 flex items-center gap-1.5 text-sm">
                  <span>2. Kisa k ap pase apre enstalasyon an ?</span>
                </span>
                <ul className="list-disc pl-4 space-y-1 text-slate-300">
                  <li>UpMizik Admin ap parèt dirèkteman sou <strong>Bureau (Desktop)</strong> ou ak nan <strong>Taskbar</strong> Windows la.</li>
                  <li>Li pral louvri nan yon fenèt separe, pwòp, san okenn ba navigatè.</li>
                  <li>L ap resevwa tout demand atis ak prèv transfè an dirèk toutotan PC a konekte sou entènèt.</li>
                </ul>
              </div>
            </div>

            <div className="flex gap-3">
              {isInstallable && (
                <button
                  type="button"
                  onClick={async () => {
                    await install();
                    setShowGuide(false);
                  }}
                  className="flex-1 py-3 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-yellow-500 text-slate-950 shadow-lg shadow-amber-500/30 flex items-center justify-center gap-2"
                >
                  <Download className="w-4 h-4" />
                  <span>Enstale Kounye a</span>
                </button>
              )}
              <button
                type="button"
                onClick={() => setShowGuide(false)}
                className="flex-1 py-3 rounded-xl text-xs font-bold bg-white/[0.08] hover:bg-white/[0.12] text-white border border-white/[0.1]"
              >
                Mwen Konprann
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};
