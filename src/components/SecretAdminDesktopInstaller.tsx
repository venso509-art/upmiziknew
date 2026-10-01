import React, { useState, useEffect } from 'react';
import { Monitor, Download, ShieldCheck, CheckCircle2, Lock, X, HelpCircle, ArrowRight } from 'lucide-react';
import { usePWAInstall } from '../hooks/usePWAInstall';

export const SECRET_INSTALL_PARAM = 'admin_install_secret=upmizik_desk_clauvens_2026';

interface SecretAdminDesktopInstallerProps {
  onClose?: () => void;
  onInstalledSuccess?: () => void;
}

export const SecretAdminDesktopInstaller: React.FC<SecretAdminDesktopInstallerProps> = ({
  onClose,
  onInstalledSuccess
}) => {
  const { isInstallable, isInstalled, install } = usePWAInstall();
  const [isInstalling, setIsInstalling] = useState(false);
  const [showInstructions, setShowInstructions] = useState(false);

  useEffect(() => {
    if (isInstalled && onInstalledSuccess) {
      onInstalledSuccess();
    }
  }, [isInstalled, onInstalledSuccess]);

  const handleInstallClick = async () => {
    setIsInstalling(true);
    try {
      const success = await install();
      if (success && onInstalledSuccess) {
        onInstalledSuccess();
      }
    } finally {
      setIsInstalling(false);
    }
  };

  const handleClose = () => {
    // Clear the secret param from URL so it doesn't stay in browser history
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href);
      url.searchParams.delete('admin_install_secret');
      window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
    }
    if (onClose) onClose();
  };

  return (
    <div className="fixed inset-0 z-[500] bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 animate-fadeIn">
      <div className="relative w-full max-w-md bg-[#070b16] border-2 border-amber-500/60 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 text-slate-100">
        {/* Close Button */}
        {onClose && (
          <button
            type="button"
            onClick={handleClose}
            className="absolute top-4 right-4 p-2 rounded-full text-slate-400 hover:text-white bg-white/[0.05] hover:bg-white/[0.1] transition-colors"
            title="Fèmen"
          >
            <X className="w-5 h-5" />
          </button>
        )}

        {/* Security Header Badge */}
        <div className="flex items-center gap-2">
          <span className="px-3 py-1 rounded-full bg-red-500/20 text-red-400 border border-red-500/30 text-[10px] font-mono font-bold uppercase tracking-wider flex items-center gap-1.5">
            <Lock className="w-3 h-3" />
            <span>Pòtay Prive • Administratè Sèlman</span>
          </span>
        </div>

        {/* Icon & App Title */}
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-400 to-yellow-500 text-slate-950 flex items-center justify-center shrink-0 shadow-xl shadow-amber-500/20 border border-amber-300">
            <Monitor className="w-8 h-8" />
          </div>
          <div>
            <h2 className="text-xl font-black text-white font-['Cabinet_Grotesk',sans-serif] tracking-tight">
              UpMizik Admin Desk
            </h2>
            <p className="text-xs text-amber-300 font-medium">
              Aplikasyon Biwo Dedye (Standalone Desktop)
            </p>
          </div>
        </div>

        {/* Info Description */}
        <div className="bg-white/[0.04] border border-white/[0.08] rounded-2xl p-4 text-xs text-slate-300 space-y-2 leading-relaxed">
          <p>
            Lyen prive sa a fèt sèlman pou ou menm ki se pwopriyetè ak sipè-administratè a pou w ka enstale lojisyèl la sou machin ou.
          </p>
          <p className="text-slate-400">
            Pèsonn nan piblik la pa gen aksè ak paj sa a, ni yo pap wè okenn bouton pou telechaje l sou sit la.
          </p>
        </div>

        {/* Status / Action Button */}
        {isInstalled ? (
          <div className="bg-emerald-500/15 border border-emerald-500/30 rounded-2xl p-4 text-center space-y-2">
            <div className="flex items-center justify-center gap-2 text-emerald-400 font-bold text-sm">
              <CheckCircle2 className="w-5 h-5" />
              <span>Aplikasyon an deja enstale sou machin sa a !</span>
            </div>
            <p className="text-xs text-slate-300">
              Ou ka ouvri l dirèkteman sou biwo w (Desktop) oswa nan meni aplikasyon w yo.
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            <button
              type="button"
              onClick={handleInstallClick}
              disabled={isInstalling}
              className="w-full py-4 rounded-2xl font-black text-sm bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 hover:from-amber-300 hover:to-yellow-300 text-slate-950 shadow-xl shadow-amber-500/30 flex items-center justify-center gap-2.5 transition-all active:scale-98 disabled:opacity-50"
            >
              <Download className="w-5 h-5" />
              <span>{isInstalling ? 'Enstalasyon an ap fèt...' : 'Enstale sou Machin Mwen Kounye a'}</span>
            </button>

            <button
              type="button"
              onClick={() => setShowInstructions(!showInstructions)}
              className="w-full text-center text-xs text-slate-400 hover:text-white flex items-center justify-center gap-1.5 py-1"
            >
              <HelpCircle className="w-3.5 h-3.5" />
              <span>Kijan pou w konfime enstalasyon an si navigatè w mande l?</span>
            </button>
          </div>
        )}

        {/* Step-by-step fallback guide if needed */}
        {showInstructions && (
          <div className="bg-[#05070a] border border-amber-500/30 rounded-2xl p-4 text-xs text-slate-300 space-y-2 animate-fadeIn">
            <p className="font-bold text-amber-300">Si bouton an pa deklannche otomatikman :</p>
            <ol className="list-decimal list-inside space-y-1 text-slate-300">
              <li>Gade anlè nan ba adrès Chrome/Edge ou a (sou bò dwat).</li>
              <li>Klike sou ti ikon ekran ak flèch ki make <strong>"Install UpMizik Admin Desk"</strong>.</li>
              <li>Klike <strong>Install</strong> pou l mete l sou biwo w.</li>
            </ol>
          </div>
        )}

        {/* Security Footer Note */}
        <div className="pt-2 border-t border-white/[0.06] flex items-center justify-between text-[11px] text-slate-500">
          <span>Koneksyon Dirèk: MySQL VPS Coolify</span>
          <span className="font-mono text-emerald-400">Sekirize • SSL</span>
        </div>
      </div>
    </div>
  );
};
