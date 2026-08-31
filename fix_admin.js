const fs = require("fs");
const file = "./src/components/AdminDashboard.tsx";
const content = fs.readFileSync(file, "utf8");
const target = `<label className="block text-xs font-bold text-slate-300 mb-1">Vil Rezidans</label>`;
const idx = content.indexOf(target);
if (idx !== -1) {
  const cleanPrefix = content.substring(0, idx + target.length);
  const cleanSuffix = `
                  <input
                    type="text"
                    value={manualArtistCity ?? ""}
                    onChange={(e) => setManualArtistCity(e.target.value)}
                    placeholder="Eg: Pòtoprens"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-300 mb-1">Kòd PIN Sekirite (4 Chif) *</label>
                  <input
                    type="password"
                    maxLength={4}
                    value={manualArtistPin ?? ""}
                    onChange={(e) => setManualArtistPin(e.target.value)}
                    placeholder="Eg: 1234"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-300 mb-1">Rasin Mizikal (Eg: Rasin, Konpa, Rap)</label>
                  <input
                    type="text"
                    value={manualArtistRoots ?? ""}
                    onChange={(e) => setManualArtistRoots(e.target.value)}
                    placeholder="Eg: Mizik Rasin & Vodou Tradisyonèl"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-300 mb-1">Enfliyan Mizikal</label>
                  <input
                    type="text"
                    value={manualArtistInfluences ?? ""}
                    onChange={(e) => setManualArtistInfluences(e.target.value)}
                    placeholder="Eg: Boukman Eksperyans, RAM, Coupé Cloué"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-300 mb-1">URL Foto Profil (Avatar)</label>
                  <input
                    type="url"
                    value={manualArtistAvatar ?? ""}
                    onChange={(e) => setManualArtistAvatar(e.target.value)}
                    placeholder="https://..."
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-300 mb-1">URL Prèv Peman $4.99 oswa ID</label>
                  <input
                    type="url"
                    value={manualArtistProof ?? ""}
                    onChange={(e) => setManualArtistProof(e.target.value)}
                    placeholder="https://..."
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-300 mb-1">Biyografi Atis</label>
                  <textarea
                    rows={3}
                    value={manualArtistBio ?? ""}
                    onChange={(e) => setManualArtistBio(e.target.value)}
                    placeholder="Ekri kèk liy sou karyè ak istwa atis la..."
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none resize-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-300 mb-1">Instagram (@username)</label>
                  <input
                    type="text"
                    value={manualArtistInstagram ?? ""}
                    onChange={(e) => setManualArtistInstagram(e.target.value)}
                    placeholder="@nom_instagram"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-300 mb-1">TikTok (@username)</label>
                  <input
                    type="text"
                    value={manualArtistTiktok ?? ""}
                    onChange={(e) => setManualArtistTiktok(e.target.value)}
                    placeholder="@nom_tiktok"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-300 mb-1">Twitter / X (@username)</label>
                  <input
                    type="text"
                    value={manualArtistTwitter ?? ""}
                    onChange={(e) => setManualArtistTwitter(e.target.value)}
                    placeholder="@nom_twitter"
                    className="w-full bg-[#05070a] border border-white/[0.15] focus:border-amber-400 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-300 mb-2">Estati Inisyal Dosye a</label>
                  <div className="grid grid-cols-2 gap-3">
                    <label className={"flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all " + (manualArtistStatus === "pending" ? "bg-amber-500/20 border-amber-500/50 text-amber-300" : "bg-[#05070a] border-white/[0.1] text-slate-400")}>
                      <input
                        type="radio"
                        name="manualArtistStatus"
                        value="pending"
                        checked={manualArtistStatus === "pending"}
                        onChange={() => setManualArtistStatus("pending")}
                        className="sr-only"
                      />
                      <Clock className="w-4 h-4" />
                      <span className="text-xs font-semibold">An Atant (Pending)</span>
                    </label>

                    <label className={"flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all " + (manualArtistStatus === "active" ? "bg-emerald-500/20 border-emerald-500/50 text-emerald-300" : "bg-[#05070a] border-white/[0.1] text-slate-400")}>
                      <input
                        type="radio"
                        name="manualArtistStatus"
                        value="active"
                        checked={manualArtistStatus === "active"}
                        onChange={() => setManualArtistStatus("active")}
                        className="sr-only"
                      />
                      <CheckCircle className="w-4 h-4" />
                      <span className="text-xs font-semibold">Valide Dirèkteman (Active)</span>
                    </label>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-white/[0.08]">
                <button
                  type="button"
                  onClick={() => setShowAddManualArtistModal(false)}
                  className="px-4 py-2.5 rounded-xl border border-white/[0.15] hover:bg-white/[0.06] text-slate-300 text-xs font-semibold transition-colors"
                >
                  Anile
                </button>
                <button
                  type="submit"
                  className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-bold text-xs shadow-lg shadow-amber-500/20 transition-all flex items-center gap-2"
                >
                  <PlusCircle className="w-4 h-4" />
                  Kreye Dosye Atis la
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminDashboard;
`;
  fs.writeFileSync(file, cleanPrefix + cleanSuffix);
  console.log("✅ AdminDashboard.tsx netwaye!");
}
