/**
 * Step Info Metadato
 *
 * @package RNDT_Manager
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepMetadataInfo Component
 */
const StepMetadataInfo = ({ codelists, settings }) => {
    const { metadata, setField, setFields, getFieldError } = useMetadata();

    // Se file_identifier manca (fallback), genera UUID con IPA
    useEffect(() => {
        if (!metadata.file_identifier) {
            const uuid = generateUUID();
            const ipaCode = settings?.defaultIpaCode || '';
            const identifier = ipaCode ? `${ipaCode}:${uuid}` : uuid;
            setFields({
                file_identifier: identifier,
                resource_identifier: identifier,
            });
        }
    }, []);

    // Opzioni lingua
    const languageOptions = codelists?.languages || [
        { value: 'ita', label: 'Italiano' },
        { value: 'eng', label: 'Inglese' },
        { value: 'deu', label: 'Tedesco' },
        { value: 'fra', label: 'Francese' },
    ];

    // Opzioni charset
    const charsetOptions = [
        { value: 'utf8', label: 'UTF-8' },
        { value: 'utf16', label: 'UTF-16' },
        { value: '8859part1', label: 'ISO 8859-1' },
        { value: '8859part15', label: 'ISO 8859-15' },
    ];

    // Genera UUID v4
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Imposta data corrente se vuota
    useEffect(() => {
        if (!metadata.metadata_date) {
            const today = new Date().toISOString().split('T')[0];
            setField('metadata_date', today);
        }
    }, []);

    return (
        <div className="rndt-step rndt-step--metadata-info">
            <h3>{__('Informazioni sul metadato', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Informazioni identificative del record di metadato.', 'rndt-manager')}
            </p>

            {/* File identifier (IPA:UUID, read-only) */}
            <FieldWrapper
                label={__('Identificativo del metadato', 'rndt-manager')}
                required={true}
                error={getFieldError('file_identifier')}
                help={__('Identificativo univoco codice_iPA:UUID. Impostato automaticamente e uguale all\'identificativo della risorsa.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.file_identifier || ''}
                    readOnly
                />
            </FieldWrapper>

            {/* Lingua metadato */}
            <FieldWrapper
                label={__('Lingua del metadato', 'rndt-manager')}
                required={true}
                error={getFieldError('metadata_language')}
            >
                <SelectControl
                    value={metadata.metadata_language || 'ita'}
                    options={languageOptions}
                    onChange={(value) => setField('metadata_language', value)}
                />
            </FieldWrapper>

            {/* Character set */}
            <FieldWrapper
                label={__('Set di caratteri', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.metadata_character_set || 'utf8'}
                    options={charsetOptions}
                    onChange={(value) => setField('metadata_character_set', value)}
                />
            </FieldWrapper>

            {/* Data metadato */}
            <FieldWrapper
                label={__('Data del metadato', 'rndt-manager')}
                required={true}
                error={getFieldError('metadata_date')}
                help={__('Data di creazione o ultimo aggiornamento del metadato.', 'rndt-manager')}
            >
                <TextControl
                    type="date"
                    value={metadata.metadata_date || ''}
                    onChange={(value) => setField('metadata_date', value)}
                />
            </FieldWrapper>

            {/* Standard name */}
            <FieldWrapper
                label={__('Nome standard metadati', 'rndt-manager')}
                help={__('Valore preimpostato secondo il profilo RNDT.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.metadata_standard_name || 'DM - Regole tecniche RNDT'}
                    onChange={(value) => setField('metadata_standard_name', value)}
                    readOnly
                />
            </FieldWrapper>

            {/* Standard version */}
            <FieldWrapper
                label={__('Versione standard metadati', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.metadata_standard_version || '10 novembre 2011'}
                    onChange={(value) => setField('metadata_standard_version', value)}
                    readOnly
                />
            </FieldWrapper>

            {/* Parent identifier (per serie) */}
            {metadata.resource_type === 'series' && (
                <FieldWrapper
                    label={__('Identificativo parent', 'rndt-manager')}
                    help={__('UUID del metadato parent (per serie).', 'rndt-manager')}
                >
                    <TextControl
                        value={metadata.parent_identifier || ''}
                        onChange={(value) => setField('parent_identifier', value)}
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    />
                </FieldWrapper>
            )}
        </div>
    );
};

export default StepMetadataInfo;
