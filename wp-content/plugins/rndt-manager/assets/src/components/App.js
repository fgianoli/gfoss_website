/**
 * App Component principale
 *
 * @package RNDT_Manager
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Snackbar, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Wizard from './Wizard/Wizard';
import ResourceTypeSelector from './Wizard/ResourceTypeSelector';
import ImportXmlModal from './Modals/ImportXmlModal';
import { MetadataProvider } from '../context/MetadataContext';
import useApi from '../hooks/useApi';

/**
 * Componente App
 *
 * @param {Object} props
 * @param {Object} props.initialData Dati iniziali dal server
 */
const App = ({ initialData }) => {
    const [resourceType, setResourceType] = useState(initialData.resourceType);
    const [metadataId, setMetadataId] = useState(initialData.metadataId);
    const [metadata, setMetadata] = useState(null);
    const [loading, setLoading] = useState(!!initialData.metadataId);
    const [notices, setNotices] = useState([]);
    const [showTypeSelector, setShowTypeSelector] = useState(!initialData.metadataId);
    const [showImportModal, setShowImportModal] = useState(false);
    const [importing, setImporting] = useState(false);

    const {
        fetchMetadata,
        createMetadata,
        updateMetadata,
        validateMetadata,
        exportXml,
        previewXml,
        publishToCsw,
        publishToGeoServer,
        fetchGeoServerLayers,
        importXml,
        previewImport,
    } = useApi(initialData);

    // Carica metadato esistente
    useEffect(() => {
        if (initialData.metadataId) {
            loadMetadata(initialData.metadataId);
        }
    }, [initialData.metadataId]);

    /**
     * Carica metadato dal server
     */
    const loadMetadata = async (id) => {
        setLoading(true);
        try {
            const data = await fetchMetadata(id);
            setMetadata(data);
            setResourceType(data.resource_type || 'dataset');
            setShowTypeSelector(false);
        } catch (error) {
            addNotice('error', error.message || __('Errore nel caricamento del metadato.', 'rndt-manager'));
        } finally {
            setLoading(false);
        }
    };

    /**
     * Gestisce selezione tipo risorsa
     */
    const handleResourceTypeSelect = (type) => {
        setResourceType(type);
        setShowTypeSelector(false);

        // Genera UUID e combina con codice IPA da impostazioni
        const uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
        const ipaCode = initialData.settings?.defaultIpaCode || '';
        const identifier = ipaCode ? `${ipaCode}:${uuid}` : uuid;

        setMetadata({
            resource_type: type,
            metadata_language: 'ita',
            metadata_character_set: 'utf8',
            metadata_standard_name: 'DM - Regole tecniche RNDT',
            metadata_standard_version: '10 novembre 2011',
            file_identifier: identifier,
            resource_identifier: identifier,
        });
    };

    /**
     * Salva metadato
     */
    const handleSave = useCallback(async (data, options = {}) => {
        try {
            let result;
            if (metadataId) {
                result = await updateMetadata(metadataId, data);
            } else {
                result = await createMetadata(data);
                if (result.id) {
                    setMetadataId(result.id);
                    // Aggiorna URL senza ricaricare
                    const newUrl = new URL(window.location.href);
                    // Admin usa 'id', frontend usa 'rndt_id'
                    const paramName = newUrl.searchParams.has('page') ? 'id' : 'rndt_id';
                    newUrl.searchParams.set(paramName, result.id);
                    window.history.replaceState({}, '', newUrl);
                }
            }

            setMetadata({ ...metadata, ...data });
            addNotice('success', __('Metadato salvato con successo.', 'rndt-manager'));

            // Valida automaticamente se richiesto
            if (options.validate && result.id) {
                await handleValidate(result.id);
            }

            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nel salvataggio.', 'rndt-manager'));
            throw error;
        }
    }, [metadataId, metadata, updateMetadata, createMetadata]);

    /**
     * Valida metadato
     */
    const handleValidate = useCallback(async (id) => {
        try {
            const targetId = id || metadataId;
            if (!targetId) {
                addNotice('error', __('Salva prima il metadato.', 'rndt-manager'));
                return null;
            }

            const result = await validateMetadata(targetId);

            if (result.valid) {
                addNotice('success', __('Metadato valido!', 'rndt-manager'));
            } else {
                const errorCount = result.errors?.length || 0;
                addNotice('warning',
                    `${__('Trovati', 'rndt-manager')} ${errorCount} ${__('errori di validazione.', 'rndt-manager')}`
                );
            }

            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nella validazione.', 'rndt-manager'));
            throw error;
        }
    }, [metadataId, validateMetadata]);

    /**
     * Scarica XML
     */
    const handleDownloadXml = useCallback(async () => {
        try {
            if (!metadataId) {
                addNotice('error', __('Salva prima il metadato.', 'rndt-manager'));
                return;
            }

            // Usa apiFetch (con nonce) per ottenere l'XML, poi scarica via blob
            const result = await exportXml(metadataId);

            if (result && result.xml) {
                const blob = new Blob([result.xml], { type: 'application/xml' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                // Filename = IPA:UUID (come da specifiche RNDT)
                const xmlFilename = result.file_identifier || metadata?.file_identifier || `metadata-${metadataId}`;
                a.download = xmlFilename.replace(/:/g, '_') + '.xml';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                addNotice('success', __('Download XML completato.', 'rndt-manager'));
            }
        } catch (error) {
            addNotice('error', error.message || __('Errore nel download XML.', 'rndt-manager'));
        }
    }, [metadataId, exportXml]);

    /**
     * Anteprima XML
     */
    const handlePreviewXml = useCallback(async () => {
        try {
            if (!metadataId) {
                addNotice('error', __('Salva prima il metadato.', 'rndt-manager'));
                return null;
            }

            const result = await previewXml(metadataId);
            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nel generare anteprima XML.', 'rndt-manager'));
            throw error;
        }
    }, [metadataId, previewXml]);

    /**
     * Pubblica su CSW
     */
    const handlePublishCsw = useCallback(async () => {
        try {
            if (!metadataId) {
                addNotice('error', __('Salva prima il metadato.', 'rndt-manager'));
                return null;
            }

            const result = await publishToCsw(metadataId);

            if (result.success) {
                addNotice('success', __('Metadato pubblicato su CSW con successo!', 'rndt-manager'));
            } else {
                addNotice('error', result.message || __('Errore nella pubblicazione CSW.', 'rndt-manager'));
            }

            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nella pubblicazione CSW.', 'rndt-manager'));
            throw error;
        }
    }, [metadataId, publishToCsw]);

    /**
     * Associa layer GeoServer
     */
    const handlePublishGeoServer = useCallback(async (layerName) => {
        try {
            if (!metadataId) {
                addNotice('error', __('Salva prima il metadato.', 'rndt-manager'));
                return null;
            }

            const result = await publishToGeoServer(metadataId, layerName);

            if (result.success) {
                addNotice('success', result.message || __('Layer GeoServer associato con successo!', 'rndt-manager'));
                // Ricarica metadato per aggiornare le online_resources
                await loadMetadata(metadataId);
            } else {
                addNotice('error', result.message || __('Errore nell\'associazione del layer.', 'rndt-manager'));
            }

            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nell\'associazione del layer.', 'rndt-manager'));
            throw error;
        }
    }, [metadataId, publishToGeoServer]);

    /**
     * Import XML
     */
    const handleImportXml = useCallback(async (xml, options = {}) => {
        try {
            // Import da CSW usa endpoint diverso
            if (options.csw_url) {
                const result = await importXml(null, options);
                if (result.id) {
                    addNotice('success', __('Metadato importato da CSW con successo!', 'rndt-manager'));
                    setMetadataId(result.id);
                    await loadMetadata(result.id);
                    setShowTypeSelector(false);
                    // Aggiorna URL
                    const newUrl = new URL(window.location.href);
                    const paramName = newUrl.searchParams.has('page') ? 'id' : 'rndt_id';
                    newUrl.searchParams.set(paramName, result.id);
                    window.history.replaceState({}, '', newUrl);
                }
                return result;
            }

            const result = await importXml(xml, options);
            if (result.id) {
                addNotice('success', __('Metadato importato con successo!', 'rndt-manager'));
                setMetadataId(result.id);
                await loadMetadata(result.id);
                setShowTypeSelector(false);
                // Aggiorna URL
                const newUrl = new URL(window.location.href);
                const paramName = newUrl.searchParams.has('page') ? 'id' : 'rndt_id';
                newUrl.searchParams.set(paramName, result.id);
                window.history.replaceState({}, '', newUrl);
            }
            return result;
        } catch (error) {
            addNotice('error', error.message || __('Errore nell\'importazione.', 'rndt-manager'));
            throw error;
        }
    }, [importXml, loadMetadata]);

    /**
     * Aggiungi notifica
     */
    const addNotice = (status, message) => {
        const id = Date.now();
        setNotices(prev => [...prev, { id, status, message }]);

        // Auto-rimuovi dopo 5 secondi
        setTimeout(() => {
            setNotices(prev => prev.filter(n => n.id !== id));
        }, 5000);
    };

    /**
     * Rimuovi notifica
     */
    const removeNotice = (id) => {
        setNotices(prev => prev.filter(n => n.id !== id));
    };

    // Loading state
    if (loading) {
        return (
            <div className="rndt-app rndt-app--loading">
                <Spinner />
                <p>{__('Caricamento in corso...', 'rndt-manager')}</p>
            </div>
        );
    }

    // Wrapper import dal tipo selector
    const handleImportFromSelector = async (xml, options) => {
        setImporting(true);
        try {
            await handleImportXml(xml, options);
            setShowImportModal(false);
        } finally {
            setImporting(false);
        }
    };

    // Selezione tipo risorsa
    if (showTypeSelector) {
        return (
            <div className="rndt-app">
                <ResourceTypeSelector
                    onSelect={handleResourceTypeSelect}
                    codelists={initialData.codelists}
                    onImportXml={() => setShowImportModal(true)}
                />
                <ImportXmlModal
                    isOpen={showImportModal}
                    onClose={() => setShowImportModal(false)}
                    onImport={handleImportFromSelector}
                    onPreview={previewImport}
                    importing={importing}
                />
            </div>
        );
    }

    // Wizard principale
    return (
        <MetadataProvider
            initialMetadata={metadata}
            resourceType={resourceType}
            codelists={initialData.codelists}
        >
            <div className="rndt-app">
                {/* Notifiche */}
                <div className="rndt-notices">
                    {notices.map(notice => (
                        <Snackbar
                            key={notice.id}
                            status={notice.status}
                            onRemove={() => removeNotice(notice.id)}
                        >
                            {notice.message}
                        </Snackbar>
                    ))}
                </div>

                {/* Wizard */}
                <Wizard
                    metadataId={metadataId}
                    resourceType={resourceType}
                    onSave={handleSave}
                    onValidate={handleValidate}
                    onDownloadXml={handleDownloadXml}
                    onPreviewXml={handlePreviewXml}
                    onPublishCsw={handlePublishCsw}
                    onPublishGeoServer={handlePublishGeoServer}
                    onImportXml={handleImportXml}
                    onPreviewImport={previewImport}
                    fetchGeoServerLayers={fetchGeoServerLayers}
                    codelists={initialData.codelists}
                    i18n={initialData.i18n}
                    settings={initialData.settings}
                />
            </div>
        </MetadataProvider>
    );
};

export default App;
