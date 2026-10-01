import React, { useState, useEffect } from 'react';

interface AppSplashScreenProps {
  onFinish?: () => void;
  minDisplayTimeMs?: number;
}

export const AppSplashScreen: React.FC<AppSplashScreenProps> = ({
  onFinish,
  minDisplayTimeMs = 1800
}) => {
  const [progress, setProgress] = useState(12);
  const [statusText, setStatusText] = useState('Chajman mizik & pwofil atis yo...');
  const [isFadingOut, setIsFadingOut] = useState(false);
  const [isRemoved, setIsRemoved] = useState(false);

  useEffect(() => {
    const startTime = Date.now();

    // Rotate status messages smoothly
    const statusTimeout1 = setTimeout(() => {
      setStatusText('Koneksyon ak platfòm kreyòl la...');
    }, 600);

    const statusTimeout2 = setTimeout(() => {
      setStatusText('Byenveni sou UpMizik Ayiti!');
    }, 1250);

    // Progress bar animation loop
    const interval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      const pct = Math.min(100, Math.round((elapsed / minDisplayTimeMs) * 100));
      setProgress(pct);

      if (elapsed >= minDisplayTimeMs) {
        clearInterval(interval);
        setProgress(100);

        // Initiate smooth fade out
        setIsFadingOut(true);
        const exitTimeout = setTimeout(() => {
          setIsRemoved(true);
          onFinish?.();
        }, 550);

        return () => clearTimeout(exitTimeout);
      }
    }, 30);

    return () => {
      clearInterval(interval);
      clearTimeout(statusTimeout1);
      clearTimeout(statusTimeout2);
    };
  }, [minDisplayTimeMs, onFinish]);

  if (isRemoved) return null;

  return (
    <div
      role="status"
      aria-live="polite"
      aria-label="Chajman UpMizik..."
      className={`fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#05070a] text-white select-none transition-all duration-500 ease-out ${
        isFadingOut
          ? 'opacity-0 scale-102 pointer-events-none backdrop-blur-none'
          : 'opacity-100 scale-100'
      }`}
    >
      {/* Haitian Flag Accent Top Line */}
      <div className="absolute top-0 left-0 right-0 h-1 flex w-full z-10">
        <div className="w-1/2 bg-blue-600 shadow-[0_0_12px_rgba(37,99,235,0.8)]"></div>
        <div className="w-1/2 bg-red-600 shadow-[0_0_12px_rgba(220,38,38,0.8)]"></div>
      </div>

      {/* Atmospheric Background Ambient Light Orbs */}
      <div className="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-blue-600/15 blur-3xl pointer-events-none animate-pulse"></div>
      <div className="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-red-600/15 blur-3xl pointer-events-none animate-pulse"></div>

      {/* Central Brand Loading Card */}
      <div className="relative flex flex-col items-center text-center px-6 max-w-sm w-full">
        {/* Animated Glow Halo */}
        <div className="relative mb-6">
          <div className="absolute -inset-4 rounded-full bg-gradient-to-tr from-blue-600/30 via-transparent to-red-600/30 blur-xl animate-pulse"></div>

          {/* Logo Disc with Rotating Soundwave Ring */}
          <div className="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gradient-to-br from-blue-900 via-[#070e1f] to-[#1a0508] p-1 shadow-2xl shadow-blue-950/80 ring-2 ring-white/10 flex items-center justify-center">
            {/* Spinning decorative ring */}
            <div className="absolute inset-0 rounded-full border border-dashed border-red-500/40 animate-[spin_6s_linear_infinite]"></div>
            
            {/* Logo Inner Image */}
            <img
              src="/upmizik-logo.svg"
              alt="UpMizik Logo"
              className="w-16 h-16 sm:w-18 sm:h-18 object-contain drop-shadow-[0_4px_12px_rgba(0,0,0,0.8)] transform transition-transform duration-300 hover:scale-105"
            />
          </div>

          {/* Live Indicator Ping */}
          <span className="absolute top-1 right-1 flex h-4 w-4">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-4 w-4 bg-red-500 border-2 border-[#05070a] shadow-sm shadow-red-500"></span>
          </span>
        </div>

        {/* Animated Equalizer Sound Bars */}
        <div className="flex items-end justify-center gap-1.5 h-7 mb-5" aria-hidden="true">
          <span className="w-1 bg-blue-500 rounded-full animate-[soundbar_0.8s_ease-in-out_infinite] h-3"></span>
          <span className="w-1 bg-blue-400 rounded-full animate-[soundbar_1.1s_ease-in-out_infinite_0.15s] h-5"></span>
          <span className="w-1 bg-yellow-400 rounded-full animate-[soundbar_0.7s_ease-in-out_infinite_0.3s] h-6"></span>
          <span className="w-1 bg-red-500 rounded-full animate-[soundbar_0.9s_ease-in-out_infinite_0.1s] h-7"></span>
          <span className="w-1 bg-red-400 rounded-full animate-[soundbar_1.2s_ease-in-out_infinite_0.25s] h-4"></span>
          <span className="w-1 bg-yellow-300 rounded-full animate-[soundbar_0.65s_ease-in-out_infinite_0.4s] h-5"></span>
          <span className="w-1 bg-blue-400 rounded-full animate-[soundbar_1s_ease-in-out_infinite_0.2s] h-3"></span>
        </div>

        {/* Brand Name: UPMIZIK */}
        <div className="flex items-center justify-center gap-2 mb-2">
          <h1 className="text-3xl sm:text-4xl font-black tracking-tight font-['Cabinet_Grotesk',sans-serif]">
            <span className="text-red-500 drop-shadow-[0_0_20px_rgba(239,68,68,0.6)]">Up</span><span className="text-blue-500 drop-shadow-[0_0_20px_rgba(37,99,235,0.6)]">Mizik</span>
          </h1>
          <span className="text-[11px] font-extrabold uppercase tracking-widest bg-yellow-400 text-slate-950 px-2 py-0.5 rounded shadow-md">
            Ayiti
          </span>
        </div>

        {/* Brand Slogan */}
        <p className="text-xs sm:text-sm text-slate-400 font-medium mb-6">
          Platfòm Mizik Ayisyen & Sipò Dirèk pou Atis
        </p>

        {/* Modern Sleek Progress Bar */}
        <div className="w-full bg-white/10 rounded-full h-1.5 p-0.5 overflow-hidden backdrop-blur-sm mb-3">
          <div
            className="h-full rounded-full bg-gradient-to-r from-blue-500 via-yellow-400 to-red-500 transition-all duration-75 ease-out shadow-[0_0_10px_rgba(239,68,68,0.7)]"
            style={{ width: `${progress}%` }}
          ></div>
        </div>

        {/* Progress Metrics & Status Text */}
        <div className="w-full flex items-center justify-between text-[11px] font-mono text-slate-400 px-0.5">
          <span className="text-slate-300 truncate max-w-[220px]">
            {statusText}
          </span>
          <span className="font-semibold text-white/90">
            {progress}%
          </span>
        </div>
      </div>

      {/* Discreet Direct Enter Link (Skip option) */}
      <button
        type="button"
        onClick={() => {
          setIsFadingOut(true);
          setTimeout(() => {
            setIsRemoved(true);
            onFinish?.();
          }, 300);
        }}
        className="absolute bottom-6 text-[11px] text-slate-500 hover:text-slate-300 transition-colors uppercase tracking-widest py-1 px-3 rounded hover:bg-white/5 cursor-pointer"
      >
        Antre dirèk &rarr;
      </button>

      {/* Embedded CSS for custom keyframe animations */}
      <style>{`
        @keyframes soundbar {
          0%, 100% {
            height: 6px;
            opacity: 0.6;
          }
          50% {
            height: 24px;
            opacity: 1;
          }
        }
      `}</style>
    </div>
  );
};
