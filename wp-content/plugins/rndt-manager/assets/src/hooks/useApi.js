/**
 * Hook per chiamate API
 *
 * @package RNDT_Manager
 */

import { useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Hook useApi
 *
 * @param {Object} config Configurazione API
 * @returns {Object} Metodi API
 */
const useApi = (config = {}) => {
    const { apiUrl = '/wp-json/rndt/v1/', nonce = '' } = config;

    // Configura apiFetch
    if (nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(nonce));
    }

    /**
     * Fetch generico
     */
    const doFetch = useCallback(async (endpoint, options = {}) => {
        try {
            const response = await apiFetch({
                path: `rndt/v1/${endpoint}`,
                ...options,
            });
            return response;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }, []);

    /**
     * Ottieni metadato
     */
    const fetchMetadata = useCallback(async (id) => {
        return doFetch(`metadata/${id}`);
    }, [doFetch]);

    /**
     * Lista metadati
     */
    const fetchMetadataList = useCallback(async (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return doFetch(`metadata${queryString ? '?' + queryString : ''}`);
    }, [doFetch]);

    /**
     * Crea metadato
     */
    const createMetadata = useCallback(async (data) => {
        return doFetch('metadata', {
            method: 'POST',
            data,
        });
    }, [doFetch]);

    /**
     * Aggiorna metadato
     */
    const updateMetadata = useCallback(async (id, data) => {
        return doFetch(`metadata/${id}`, {
            method: 'PUT',
            data,
        });
    }, [doFetch]);

    /**
     * Elimina metadato
     */
    const deleteMetadata = useCallback(async (id) => {
        return doFetch(`metadata/${id}`, {
            method: 'DELETE',
        });
    }, [doFetch]);

    /**
     * Valida metadato
     */
    const validateMetadata = useCallback(async (id, includeXsd = false) => {
        return doFetch(`validate/${id}`, {
            method: 'POST',
            data: { include_xsd: includeXsd },
        });
    }, [doFetch]);

    /**
     * Valida dati inline
     */
    const validateInline = useCallback(async (data, resourceType = 'dataset') => {
        return doFetch('validate', {
            method: 'POST',
            data: { data, resource_type: resourceType },
        });
    }, [doFetch]);

    /**
     * Esporta XML
     */
    const exportXml = useCallback(async (id, options = {}) => {
        return doFetch(`export/${id}/xml`, {
            method: 'GET',
            ...options,
        });
    }, [doFetch]);

    /**
     * Preview XML
     */
    const previewXml = useCallback(async (id) => {
        return doFetch(`export/${id}/preview`);
    }, [doFetch]);

    /**
     * Importa XML
     */
    const importXml = useCallback(async (xml, options = {}) => {
        return doFetch('import/xml', {
            method: 'POST',
            data: { xml, ...options },
        });
    }, [doFetch]);

    /**
     * Preview importazione
     */
    const previewImport = useCallback(async (xml) => {
        return doFetch('import/preview', {
            method: 'POST',
            data: { xml },
        });
    }, [doFetch]);

    /**
     * Ottieni codelist
     */
    const fetchCodelist = useCallback(async (type) => {
        return doFetch(`codelists/${type}`);
    }, [doFetch]);

    /**
     * Ottieni regole validazione
     */
    const fetchValidationRules = useCallback(async (resourceType = 'dataset') => {
        return doFetch(`validate/rules?resource_type=${resourceType}`);
    }, [doFetch]);

    /**
     * Pubblica su CSW
     */
    const publishToCsw = useCallback(async (id) => {
        return doFetch(`publish/${id}/csw`, {
            method: 'POST',
        });
    }, [doFetch]);

    /**
     * Associa layer GeoServer al metadato
     */
    const publishToGeoServer = useCallback(async (id, layerName) => {
        return doFetch(`publish/${id}/geoserver`, {
            method: 'POST',
            data: { layer_name: layerName },
        });
    }, [doFetch]);

    /**
     * Lista layer GeoServer
     */
    const fetchGeoServerLayers = useCallback(async () => {
        return doFetch('publish/geoserver/layers');
    }, [doFetch]);

    /**
     * Test connessione
     */
    const testConnection = useCallback(async (type, config) => {
        return doFetch('publish/test-connection', {
            method: 'POST',
            data: { type, config },
        });
    }, [doFetch]);

    /**
     * Lista presets parti responsabili
     */
    const fetchResponsiblePresets = useCallback(async () => {
        return doFetch('responsible-presets');
    }, [doFetch]);

    /**
     * Crea preset parte responsabile
     */
    const createResponsiblePreset = useCallback(async (data) => {
        return doFetch('responsible-presets', {
            method: 'POST',
            data,
        });
    }, [doFetch]);

    /**
     * Elimina preset parte responsabile
     */
    const deleteResponsiblePreset = useCallback(async (id) => {
        return doFetch(`responsible-presets/${id}`, {
            method: 'DELETE',
        });
    }, [doFetch]);

    return {
        // CRUD
        fetchMetadata,
        fetchMetadataList,
        createMetadata,
        updateMetadata,
        deleteMetadata,

        // Validazione
        validateMetadata,
        validateInline,
        fetchValidationRules,

        // Export/Import
        exportXml,
        previewXml,
        importXml,
        previewImport,

        // Codelist
        fetchCodelist,

        // Pubblicazione
        publishToCsw,
        publishToGeoServer,
        fetchGeoServerLayers,
        testConnection,

        // Presets parti responsabili
        fetchResponsiblePresets,
        createResponsiblePreset,
        deleteResponsiblePreset,

        // Utility
        doFetch,
    };
};

export default useApi;
