/**
 * Import XML Modal
 *
 * @package RNDT_Manager
 */

import { useState, useRef } from '@wordpress/element';
import { Button, Spinner, TextareaControl, TextControl, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * ImportXmlModal Component
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen        Modal visibility
 * @param {Function} props.onClose       Close handler
 * @param {Function} props.onImport      Import handler (xml, options) => result
 * @param {Function} props.onPreview     Preview handler (xml) => preview
 * @param {boolean}  props.importing     Import in progress
 */
const ImportXmlModal = ({ isOpen, onClose, onImport, onPreview, importing }) => {
    const [xmlContent, setXmlContent] = useState('');
    const [cswUrl, setCswUrl] = useState('');
    const [cswIdentifier, setCswIdentifier] = useState('');
    const [preview, setPreview] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [error, setError] = useState(null);
    const [overwrite, setOverwrite] = useState(false);
    const fileInputRef = useRef(null);

    const resetState = () => {
        setXmlContent('');
        setCswUrl('');
        setCswIdentifier('');
        setPreview(null);
        setError(null);
        setOverwrite(false);
    };

    const handleClose = () => {
        resetState();
        onClose();
    };

    // Upload file
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.xml')) {
            setError(__('Il file deve essere in formato XML.', 'rndt-manager'));
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            setXmlContent(event.target.result);
            setError(null);
            setPreview(null);
        };
        reader.onerror = () => {
            setError(__('Errore nella lettura del file.', 'rndt-manager'));
        };
        reader.readAsText(file);
    };

    // Drag and drop
    const handleDrop = (e) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file && file.name.toLowerCase().endsWith('.xml')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                setXmlContent(event.target.result);
                setError(null);
                setPreview(null);
            };
            reader.readAsText(file);
        }
    };

    // Preview
    const handlePreview = async () => {
        if (!xmlContent.trim()) {
            setError(__('Inserisci o carica un XML.', 'rndt-manager'));
            return;
        }

        setPreviewing(true);
        setError(null);
        try {
            const result = await onPreview(xmlContent);
            setPreview(result);
            if (result.existing) {
                setOverwrite(false);
            }
        } catch (err) {
            setError(err.message || __('Errore nell\'anteprima.', 'rndt-manager'));
        } finally {
            setPreviewing(false);
        }
    };

    // Import
    const handleImport = async () => {
        if (!xmlContent.trim()) return;

        setError(null);
        try {
            await onImport(xmlContent, { validate: true, overwrite });
            handleClose();
        } catch (err) {
            setError(err.message || __('Errore nell\'importazione.', 'rndt-manager'));
        }
    };

    // Import da CSW
    const handleCswImport = async () => {
        if (!cswUrl.trim() || !cswIdentifier.trim()) {
            setError(__('Inserisci URL endpoint e identificativo.', 'rndt-manager'));
            return;
        }

        setPreviewing(true);
        setError(null);
        try {
            // Prima importa da CSW (il backend fetch + parse automaticamente)
            await onImport(null, {
                csw_url: cswUrl,
                identifier: cswIdentifier,
                validate: true,
            });
            handleClose();
        } catch (err) {
            setError(err.message || __('Errore nell\'importazione da CSW.', 'rndt-manager'));
        } finally {
            setPreviewing(false);
        }
    };

    if (!isOpen) return null;

    const tabs = [
        {
            name: 'file',
            title: __('Carica file', 'rndt-manager'),
            className: 'rndt-import-tab',
        },
        {
            name: 'paste',
            title: __('Incolla XML', 'rndt-manager'),
            className: 'rndt-import-tab',
        },
        {
            name: 'csw',
            title: __('Importa da CSW', 'rndt-manager'),
            className: 'rndt-import-tab',
        },
    ];

    return (
        <div className="rndt-modal-overlay" onClick={handleClose}>
            <div className="rndt-modal rndt-modal--wide" onClick={(e) => e.stopPropagation()}>
                <div className="rndt-modal__header">
                    <h3>{__('Importa metadato XML', 'rndt-manager')}</h3>
                    <button
                        className="rndt-modal__close"
                        onClick={handleClose}
                        aria-label={__('Chiudi', 'rndt-manager')}
                    >
                        &times;
                    </button>
                </div>

                <div className="rndt-modal__body">
                    <TabPanel
                        className="rndt-import-tabs"
                        tabs={tabs}
                        onSelect={() => { setError(null); setPreview(null); }}
                    >
                        {(tab) => {
                            if (tab.name === 'file') {
                                return (
                                    <div className="rndt-import-panel">
                                        <div
                                            className="rndt-import-dropzone"
                                            onDrop={handleDrop}
                                            onDragOver={(e) => e.preventDefault()}
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept=".xml"
                                                onChange={handleFileChange}
                                                style={{ display: 'none' }}
                                            />
                                            <span className="dashicons dashicons-upload" />
                                            <p>
                                                {xmlContent
                                                    ? __('File caricato. Clicca per sostituire.', 'rndt-manager')
                                                    : __('Trascina un file XML qui o clicca per selezionarlo.', 'rndt-manager')
                                                }
                                            </p>
                                        </div>
                                        {xmlContent && (
                                            <p className="rndt-import-panel__size">
                                                {xmlContent.length.toLocaleString()} {__('caratteri', 'rndt-manager')}
                                            </p>
                                        )}
                                    </div>
                                );
                            }

                            if (tab.name === 'paste') {
                                return (
                                    <div className="rndt-import-panel">
                                        <TextareaControl
                                            label={__('XML ISO 19139', 'rndt-manager')}
                                            help={__('Incolla il contenuto XML del metadato.', 'rndt-manager')}
                                            value={xmlContent}
                                            onChange={(value) => { setXmlContent(value); setPreview(null); }}
                                            rows={12}
                                        />
                                    </div>
                                );
                            }

                            if (tab.name === 'csw') {
                                return (
                                    <div className="rndt-import-panel">
                                        <p className="rndt-import-panel__help">
                                            {__('Importa un metadato direttamente da un catalogo CSW.', 'rndt-manager')}
                                        </p>
                                        <TextControl
                                            label={__('Endpoint CSW', 'rndt-manager')}
                                            placeholder="https://example.com/csw"
                                            value={cswUrl}
                                            onChange={setCswUrl}
                                        />
                                        <TextControl
                                            label={__('Identificativo record', 'rndt-manager')}
                                            placeholder="UUID o file identifier"
                                            value={cswIdentifier}
                                            onChange={setCswIdentifier}
                                        />
                                        <Button
                                            variant="primary"
                                            onClick={handleCswImport}
                                            disabled={previewing || !cswUrl.trim() || !cswIdentifier.trim()}
                                        >
                                            {previewing ? (
                                                <>
                                                    <Spinner />
                                                    {__('Importazione...', 'rndt-manager')}
                                                </>
                                            ) : (
                                                __('Importa da CSW', 'rndt-manager')
                                            )}
                                        </Button>
                                    </div>
                                );
                            }

                            return null;
                        }}
                    </TabPanel>

                    {/* Errore */}
                    {error && (
                        <div className="rndt-modal__error">
                            <p>{error}</p>
                        </div>
                    )}

                    {/* Preview risultato */}
                    {preview && (
                        <div className="rndt-import-preview">
                            <h4>{__('Anteprima', 'rndt-manager')}</h4>
                            <table className="rndt-import-preview__table">
                                <tbody>
                                    <tr>
                                        <th>{__('Titolo', 'rndt-manager')}</th>
                                        <td>{preview.title || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>{__('Tipo risorsa', 'rndt-manager')}</th>
                                        <td>{preview.resource_type || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>{__('Identificativo', 'rndt-manager')}</th>
                                        <td>{preview.file_identifier || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>{__('Keywords', 'rndt-manager')}</th>
                                        <td>{preview.keywords_count || 0}</td>
                                    </tr>
                                    <tr>
                                        <th>{__('Contatti', 'rndt-manager')}</th>
                                        <td>{preview.contacts_count || 0}</td>
                                    </tr>
                                    {preview.validation && (
                                        <tr>
                                            <th>{__('Validazione', 'rndt-manager')}</th>
                                            <td>
                                                {preview.validation.valid
                                                    ? <span className="rndt-badge rndt-badge--success">{__('Valido', 'rndt-manager')}</span>
                                                    : <span className="rndt-badge rndt-badge--error">
                                                        {(preview.validation.errors?.length || 0)} {__('errori', 'rndt-manager')}
                                                      </span>
                                                }
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {preview.existing && (
                                <div className="rndt-import-preview__warning">
                                    <span className="dashicons dashicons-warning" />
                                    <p>
                                        {__('Un metadato con lo stesso identificativo esiste già:', 'rndt-manager')}
                                        {' '}<strong>{preview.existing.title}</strong> (ID: {preview.existing.id})
                                    </p>
                                    <label>
                                        <input
                                            type="checkbox"
                                            checked={overwrite}
                                            onChange={(e) => setOverwrite(e.target.checked)}
                                        />
                                        {__('Sovrascrivi metadato esistente', 'rndt-manager')}
                                    </label>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="rndt-modal__footer">
                    <Button variant="secondary" onClick={handleClose}>
                        {__('Annulla', 'rndt-manager')}
                    </Button>

                    {/* Preview button (per tab file e paste) */}
                    {xmlContent && !preview && (
                        <Button
                            variant="secondary"
                            onClick={handlePreview}
                            disabled={previewing}
                        >
                            {previewing ? (
                                <>
                                    <Spinner />
                                    {__('Anteprima...', 'rndt-manager')}
                                </>
                            ) : (
                                __('Anteprima', 'rndt-manager')
                            )}
                        </Button>
                    )}

                    {/* Import button (dopo preview) */}
                    {preview && (
                        <Button
                            variant="primary"
                            onClick={handleImport}
                            disabled={importing || (preview.existing && !overwrite)}
                        >
                            {importing ? (
                                <>
                                    <Spinner />
                                    {__('Importazione...', 'rndt-manager')}
                                </>
                            ) : (
                                __('Importa', 'rndt-manager')
                            )}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ImportXmlModal;
