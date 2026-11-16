import { initMap } from './map-core.js';
import { setupPOIs } from './map-pois.js';
import { setupUI } from './map-ui.js';
import { setupIO } from './map-io.js';

window.addEventListener('DOMContentLoaded', () => {
  const map = initMap();   // Karte initialisieren
  setupPOIs(map);          // POI-Logik initialisieren
  setupUI(map);            // Toolbar, Popups etc.
  setupIO(map);            // Import/Export

  // Nur wenn die Karte aus der Multi-User-App geöffnet wurde (?app=1):
  // Kopfleiste „‹ Übersicht" + „Aus dieser Ansicht erstellen" nachladen.
  // Dynamischer Import, damit die öffentliche Standalone-App das Modul
  // gar nicht erst lädt.
  if (new URLSearchParams(location.search).has('app')) {
    import('./app-bar.js').then(m => m.setupAppBar(map)).catch(() => {});
  }
});

