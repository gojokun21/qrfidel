import './bootstrap';

import Alpine from 'alpinejs';
import programWizard from './program-wizard';
import qrDisplay from './qr-display';
import qrScanner from './qr-scanner';
import { installPrompt, registerServiceWorker } from './pwa';

window.Alpine = Alpine;

Alpine.data('programWizard', programWizard);
Alpine.data('qrDisplay', qrDisplay);
Alpine.data('qrScanner', qrScanner);
Alpine.data('installPrompt', installPrompt);

Alpine.start();

registerServiceWorker();
