// Kopfleiste der freien Karte, wenn sie aus der Multi-User-App geöffnet wurde
// (?app=1). Zweck: zurück zum Dashboard UND aus der aktuellen Ansicht eine
// neue Präsentation anlegen. Die gezeichneten Stationen (inkl. Base64-Bilder)
// werden per sessionStorage an den Editor übergeben, der sie dort anlegt,
// die Bilder auf den Server lädt und speichert.
//
// Wird NUR per dynamischem Import aus main.js geladen, wenn ?app gesetzt ist —
// die öffentliche Standalone-App bleibt davon unberührt.
import { getSetting } from './map-settings.js';

export function setupAppBar(map) {
  document.body.classList.add('tl-app');

  const titleInp = document.getElementById('tlAppTitle');
  const createBtn = document.getElementById('tlAppCreate');
  if (!createBtn) return;

  createBtn.onclick = () => {
    if (!map.state.pois.length) {
      alert('Bitte zuerst mindestens eine Station auf der Karte setzen.');
      return;
    }
    // Zustand für den Editor einpacken (Bilder bleiben vorerst Base64 und
    // werden dort auf den Server geladen).
    const payload = {
      title: titleInp ? titleInp.value.trim() : '',
      line_mode: getSetting('lineMode'),
      profile: getSetting('profile'),
      pois: map.state.pois.map(p => ({
        lat: p.lat, lng: p.lng,
        name: p.name || '', link: p.link || '', linkText: p.linkText || '',
        img: p.img || '', createdAt: p.createdAt || ''
      }))
    };
    try {
      sessionStorage.setItem('tlHandoff', JSON.stringify(payload));
    } catch (e) {
      // sessionStorage ist knapp (~5 MB) — bei vielen großen Bildern voll.
      alert('Die Stationen konnten nicht übergeben werden (zu viele/zu große '
        + 'Bilder). Bitte in den Einstellungen eine geringere Bildqualität '
        + 'wählen und es erneut versuchen.');
      return;
    }
    location.href = 'app/editor.php?new=1';
  };
}
