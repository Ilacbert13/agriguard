// AGRIGUARD registration — barangay list from centralized API

import { initBarangaySelect } from '../shared/barangaySelect';
import { domReady } from '../shared/domReady';

domReady(() => {
    initBarangaySelect(document.getElementById('farm_barangay_code'));
});
