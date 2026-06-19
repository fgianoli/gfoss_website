/**
 * RNDT Manager - Editor Wizard React
 *
 * Entry point principale per l'applicazione React del wizard metadati.
 *
 * @package RNDT_Manager
 */

import { render } from '@wordpress/element';
import { SlotFillProvider } from '@wordpress/components';
import App from './components/App';
import './styles/main.scss';

// Mount point
const container = document.getElementById('rndt-metadata-editor');

if (container) {
    // Dati dal PHP (wp_localize_script)
    const serverData = window.rndtManager || {};

    // Recupera dati iniziali dal DOM e dal server
    const initialData = {
        metadataId: parseInt(container.dataset.metadataId, 10) || null,
        resourceType: container.dataset.resourceType || 'dataset',
        nonce: container.dataset.nonce || serverData.nonce || '',
        apiUrl: container.dataset.apiUrl || serverData.restUrl || '/wp-json/rndt/v1/',
        codelists: {
            resourceTypes: serverData.resourceTypes || {},
            inspireThemes: serverData.inspireThemes || {},
            topicCategories: serverData.topicCategories || {},
            serviceTypes: serverData.serviceTypes || {},
            roleCodes: serverData.roleCodes || {},
            restrictionCodes: serverData.restrictionCodes || {},
            epsgCodes: serverData.epsgCodes || {},
            languages: serverData.languages || {},
            charsets: serverData.charsets || {},
        },
        i18n: serverData.i18n || {},
        settings: serverData.settings || {},
    };

    render(
        <SlotFillProvider>
            <App initialData={initialData} />
        </SlotFillProvider>,
        container
    );
}
