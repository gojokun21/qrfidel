import './bootstrap';

import Alpine from 'alpinejs';
import programWizard from './program-wizard';

window.Alpine = Alpine;

Alpine.data('programWizard', programWizard);

Alpine.start();
