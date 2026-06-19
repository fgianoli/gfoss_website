/**
 * GeoServer Layer Selection Modal
 *
 * @package RNDT_Manager
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * GeoServerLayerModal Component
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen          Modal visibility
 * @param {Function} props.onClose         Close handler
 * @param {Function} props.onAssociate     Associate layer handler (layerName)
 * @param {Function} props.fetchLayers     API fetch layers
 * @param {boolean}  props.associating     Association in progress
 */
const GeoServerLayerModal = ({ isOpen, onClose, onAssociate, fetchLayers, associating }) => {
    const [layers, setLayers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [search, setSearch] = useState('');
    const [selectedLayer, setSelectedLayer] = useState(null);
    const [workspace, setWorkspace] = useState('');
    const [wmsUrl, setWmsUrl] = useState('');
    const [wfsUrl, setWfsUrl] = useState('');

    // Carica layer quando il modal si apre
    useEffect(() => {
        if (isOpen) {
            loadLayers();
        }
        return () => {
            setSearch('');
            setSelectedLayer(null);
            setError(null);
        };
    }, [isOpen]);

    const loadLayers = async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await fetchLayers();
            setLayers(result.layers || []);
            setWorkspace(result.workspace || '');
            setWmsUrl(result.wms_url || '');
            setWfsUrl(result.wfs_url || '');
        } catch (err) {
            setError(err.message || __('Errore nel caricamento dei layer.', 'rndt-manager'));
        } finally {
            setLoading(false);
        }
    };

    const handleAssociate = () => {
        if (selectedLayer) {
            onAssociate(selectedLayer);
        }
    };

    // Filtra layer
    const filteredLayers = layers.filter(name =>
        name.toLowerCase().includes(search.toLowerCase())
    );

    if (!isOpen) return null;

    return (
        <div className="rndt-modal-overlay" onClick={onClose}>
            <div className="rndt-modal" onClick={(e) => e.stopPropagation()}>
                <div className="rndt-modal__header">
                    <h3>{__('Associa layer GeoServer', 'rndt-manager')}</h3>
                    <button
                        className="rndt-modal__close"
                        onClick={onClose}
                        aria-label={__('Chiudi', 'rndt-manager')}
                    >
                        &times;
                    </button>
                </div>

                <div className="rndt-modal__body">
                    {loading && (
                        <div className="rndt-modal__loading">
                            <Spinner />
                            <p>{__('Caricamento layer da GeoServer...', 'rndt-manager')}</p>
                        </div>
                    )}

                    {error && (
                        <div className="rndt-modal__error">
                            <p>{error}</p>
                            <Button variant="secondary" onClick={loadLayers}>
                                {__('Riprova', 'rndt-manager')}
                            </Button>
                        </div>
                    )}

                    {!loading && !error && (
                        <>
                            <p className="rndt-modal__description">
                                {__('Seleziona un layer esistente su GeoServer per associarlo a questo metadato. Verranno aggiunte automaticamente le risorse WMS e WFS nella sezione distribuzione.', 'rndt-manager')}
                            </p>

                            <TextControl
                                placeholder={__('Cerca layer...', 'rndt-manager')}
                                value={search}
                                onChange={setSearch}
                                className="rndt-modal__search"
                            />

                            {filteredLayers.length === 0 ? (
                                <p className="rndt-modal__empty">
                                    {layers.length === 0
                                        ? __('Nessun layer trovato nel workspace configurato.', 'rndt-manager')
                                        : __('Nessun layer corrisponde alla ricerca.', 'rndt-manager')
                                    }
                                </p>
                            ) : (
                                <div className="rndt-layer-list">
                                    {filteredLayers.map((name) => (
                                        <div
                                            key={name}
                                            className={`rndt-layer-item ${selectedLayer === name ? 'is-selected' : ''}`}
                                            onClick={() => setSelectedLayer(name)}
                                        >
                                            <span className="rndt-layer-item__name">{name}</span>
                                            <span className="rndt-layer-item__qualified">
                                                {workspace}:{name}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Preview risorse che verranno aggiunte */}
                            {selectedLayer && (
                                <div className="rndt-layer-preview">
                                    <h4>{__('Risorse che verranno aggiunte:', 'rndt-manager')}</h4>
                                    <div className="rndt-layer-preview__item">
                                        <span className="rndt-layer-preview__protocol">OGC:WMS</span>
                                        <span className="rndt-layer-preview__url">{wmsUrl}</span>
                                        <span className="rndt-layer-preview__layer">{workspace}:{selectedLayer}</span>
                                    </div>
                                    <div className="rndt-layer-preview__item">
                                        <span className="rndt-layer-preview__protocol">OGC:WFS</span>
                                        <span className="rndt-layer-preview__url">{wfsUrl}</span>
                                        <span className="rndt-layer-preview__layer">{workspace}:{selectedLayer}</span>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <div className="rndt-modal__footer">
                    <Button variant="secondary" onClick={onClose}>
                        {__('Annulla', 'rndt-manager')}
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleAssociate}
                        disabled={!selectedLayer || associating}
                    >
                        {associating ? (
                            <>
                                <Spinner />
                                {__('Associazione...', 'rndt-manager')}
                            </>
                        ) : (
                            __('Associa layer', 'rndt-manager')
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default GeoServerLayerModal;
