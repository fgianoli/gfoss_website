/**
 * Step Identificazione
 *
 * @package RNDT_Manager
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepIdentification Component
 */
const StepIdentification = ({ codelists, resourceType, settings }) => {
    const { metadata, setField, setFields, getFieldError } = useMetadata();

    // Estrai codice IPA dal file_identifier esistente o usa default da impostazioni
    const defaultIpa = settings?.defaultIpaCode || '';
    const [ipaCode, setIpaCode] = useState(() => {
        if (metadata.file_identifier) {
            const idx = metadata.file_identifier.indexOf(':');
            if (idx > 0) {
                return metadata.file_identifier.substring(0, idx);
            }
        }
        return defaultIpa;
    });

    // Estrai parte UUID dal file_identifier
    const getUuidPart = () => {
        if (metadata.file_identifier) {
            const idx = metadata.file_identifier.indexOf(':');
            if (idx > 0) {
                return metadata.file_identifier.substring(idx + 1);
            }
            return metadata.file_identifier;
        }
        return '';
    };

    // Aggiorna identificativi quando cambia il codice IPA
    const handleIpaChange = (newIpa) => {
        setIpaCode(newIpa);
        const uuid = getUuidPart();
        if (uuid) {
            const combined = newIpa ? `${newIpa}:${uuid}` : uuid;
            setFields({
                file_identifier: combined,
                resource_identifier: combined,
            });
        }
    };

    // Sincronizza resource_identifier con file_identifier
    useEffect(() => {
        if (metadata.file_identifier && metadata.resource_identifier !== metadata.file_identifier) {
            setField('resource_identifier', metadata.file_identifier);
        }
    }, [metadata.file_identifier]);

    // Opzioni lingua
    const languageOptions = codelists?.languages || [
        { value: 'ita', label: 'Italiano' },
        { value: 'eng', label: 'Inglese' },
        { value: 'deu', label: 'Tedesco' },
        { value: 'fra', label: 'Francese' },
    ];

    return (
        <div className="rndt-step rndt-step--identification">
            <h3>{__('Identificazione della risorsa', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Informazioni di base per identificare la risorsa.', 'rndt-manager')}
            </p>

            <FieldWrapper
                label={__('Codice iPA', 'rndt-manager')}
                required={true}
                error={getFieldError('ipa_code')}
                help={__('Codice dell\'ente nel registro IndicePA. Viene usato per comporre gli identificativi.', 'rndt-manager')}
            >
                <TextControl
                    value={ipaCode}
                    onChange={handleIpaChange}
                    placeholder="agid"
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Identificativo della risorsa', 'rndt-manager')}
                required={true}
                error={getFieldError('resource_identifier')}
                help={__('Identificativo univoco composto da codice iPA:UUID. Uguale all\'identificativo del metadato.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.resource_identifier || ''}
                    readOnly
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Titolo', 'rndt-manager')}
                required={true}
                error={getFieldError('title')}
                help={__('Titolo con cui la risorsa è conosciuta.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.title || ''}
                    onChange={(value) => setField('title', value)}
                    placeholder={__('Inserisci il titolo...', 'rndt-manager')}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Titolo alternativo', 'rndt-manager')}
                help={__('Eventuale acronimo o nome breve.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.alternate_title || ''}
                    onChange={(value) => setField('alternate_title', value)}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Abstract', 'rndt-manager')}
                required={true}
                error={getFieldError('abstract')}
                help={__('Breve descrizione della risorsa.', 'rndt-manager')}
            >
                <TextareaControl
                    value={metadata.abstract || ''}
                    onChange={(value) => setField('abstract', value)}
                    rows={5}
                    placeholder={__('Descrivi la risorsa...', 'rndt-manager')}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Scopo', 'rndt-manager')}
                help={__('Motivo per cui la risorsa è stata creata.', 'rndt-manager')}
            >
                <TextareaControl
                    value={metadata.purpose || ''}
                    onChange={(value) => setField('purpose', value)}
                    rows={3}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Codespace identificativo', 'rndt-manager')}
                help={__('Namespace dell\'identificativo. Es: sito web dell\'ente (www.agenziaentrate.gov.it).', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.resource_identifier_codespace || ''}
                    onChange={(value) => setField('resource_identifier_codespace', value)}
                    placeholder="www.agid.gov.it"
                />
            </FieldWrapper>

            {resourceType !== 'service' && (
                <FieldWrapper
                    label={__('Lingua della risorsa', 'rndt-manager')}
                    required={true}
                    error={getFieldError('resource_language')}
                >
                    <SelectControl
                        value={metadata.resource_language || 'ita'}
                        options={languageOptions}
                        onChange={(value) => setField('resource_language', value)}
                    />
                </FieldWrapper>
            )}

            <FieldWrapper
                label={__('Stato', 'rndt-manager')}
                help={__('Stato di avanzamento della risorsa.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.status || ''}
                    options={[
                        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
                        { value: 'completed', label: __('Completato', 'rndt-manager') },
                        { value: 'onGoing', label: __('In corso', 'rndt-manager') },
                        { value: 'planned', label: __('Pianificato', 'rndt-manager') },
                        { value: 'underDevelopment', label: __('In sviluppo', 'rndt-manager') },
                        { value: 'historicalArchive', label: __('Archivio storico', 'rndt-manager') },
                        { value: 'obsolete', label: __('Obsoleto', 'rndt-manager') },
                    ]}
                    onChange={(value) => setField('status', value)}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Edizione', 'rndt-manager')}
                help={__('Versione della risorsa.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.edition || ''}
                    onChange={(value) => setField('edition', value)}
                />
            </FieldWrapper>

            {resourceType === 'series' && (
                <>
                    <FieldWrapper
                        label={__('Nome serie', 'rndt-manager')}
                    >
                        <TextControl
                            value={metadata.series_name || ''}
                            onChange={(value) => setField('series_name', value)}
                        />
                    </FieldWrapper>

                    <FieldWrapper
                        label={__('Identificativo serie', 'rndt-manager')}
                    >
                        <TextControl
                            value={metadata.series_issue || ''}
                            onChange={(value) => setField('series_issue', value)}
                        />
                    </FieldWrapper>
                </>
            )}
        </div>
    );
};

export default StepIdentification;
