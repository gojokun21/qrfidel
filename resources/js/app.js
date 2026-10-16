import './bootstrap';

import Alpine from 'alpinejs';
import cardPoller from './card-poller';
import programWizard from './program-wizard';
import qrDisplay from './qr-display';
import qrScanner from './qr-scanner';
import requestPoller from './request-poller';
import { installPrompt, registerServiceWorker } from './pwa';

window.Alpine = Alpine;

Alpine.data('cardPoller', cardPoller);
Alpine.data('programWizard', programWizard);
Alpine.data('qrDisplay', qrDisplay);
Alpine.data('qrScanner', qrScanner);
Alpine.data('requestPoller', requestPoller);
Alpine.data('installPrompt', installPrompt);

Alpine.start();

registerServiceWorker();
