/**
 * Step Sistema di Riferimento
 *
 * @package RNDT_Manager
 */

import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepReferenceSystem Component
 */
const StepReferenceSystem = ({ codelists, resourceType }) => {
    const { metadata, setField, getFieldError } = useMetadata();

    // Codici EPSG comuni per l'Italia
    const epsgPresets = codelists?.epsg_codes || [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'EPSG:4326', label: 'WGS 84 (EPSG:4326)' },
        { value: 'EPSG:3857', label: 'WGS 84 / Pseudo-Mercator (EPSG:3857)' },
        { value: 'EPSG:4258', label: 'ETRS89 (EPSG:4258)' },
        { value: 'EPSG:25832', label: 'ETRS89 / UTM zone 32N (EPSG:25832)' },
        { value: 'EPSG:25833', label: 'ETRS89 / UTM zone 33N (EPSG:25833)' },
        { value: 'EPSG:32632', label: 'WGS 84 / UTM zone 32N (EPSG:32632)' },
        { value: 'EPSG:32633', label: 'WGS 84 / UTM zone 33N (EPSG:32633)' },
        { value: 'EPSG:6706', label: 'RDN2008 (EPSG:6706)' },
        { value: 'EPSG:7791', label: 'RDN2008 / UTM zone 32N (EPSG:7791)' },
        { value: 'EPSG:7792', label: 'RDN2008 / UTM zone 33N (EPSG:7792)' },
        { value: 'EPSG:3003', label: 'Monte Mario / Italy zone 1 (EPSG:3003)' },
        { value: 'EPSG:3004', label: 'Monte Mario / Italy zone 2 (EPSG:3004)' },
    ];

    // Tipi rappresentazione spaziale
    const spatialRepOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'vector', label: __('Vettoriale', 'rndt-manager') },
        { value: 'grid', label: __('Raster/Grid', 'rndt-manager') },
        { value: 'textTable', label: __('Tabella testo', 'rndt-manager') },
        { value: 'tin', label: 'TIN' },
        { value: 'stereoModel', label: __('Modello stereo', 'rndt-manager') },
        { value: 'video', label: 'Video' },
    ];

    const handlePresetChange = (value) => {
        if (value) {
            setField('reference_system_code', value);
            setField('reference_system_code_space', 'EPSG');
        }
    };

    return (
        <div className="rndt-step rndt-step--reference-system">
            <h3>{__('Sistema di riferimento', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Sistema di riferimento spaziale utilizzato dalla risorsa.', 'rndt-manager')}
            </p>

            {/* Tipo rappresentazione spaziale */}
            <FieldWrapper
                label={__('Tipo di rappresentazione spaziale', 'rndt-manager')}
                required={resourceType === 'dataset'}
                error={getFieldError('spatial_representation_type')}
            >
                <SelectControl
                    value={metadata.spatial_representation_type || ''}
                    options={spatialRepOptions}
                    onChange={(value) => setField('spatial_representation_type', value)}
                />
            </FieldWrapper>

            {/* Sistema di riferimento */}
            <FieldWrapper
                label={__('Sistema di riferimento', 'rndt-manager')}
                required={resourceType === 'dataset'}
                error={getFieldError('reference_system_code')}
                help={__('Seleziona un preset o inserisci manualmente il codice EPSG.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.reference_system_code || ''}
                    options={epsgPresets}
                    onChange={handlePresetChange}
                />

                <div className="rndt-reference-system-manual">
                    <TextControl
                        label={__('Codice (manuale)', 'rndt-manager')}
                        value={metadata.reference_system_code || ''}
                        onChange={(value) => setField('reference_system_code', value)}
                        placeholder="EPSG:4326"
                    />

                    <TextControl
                        label={__('Code Space', 'rndt-manager')}
                        value={metadata.reference_system_code_space || 'EPSG'}
                        onChange={(value) => setField('reference_system_code_space', value)}
                    />
                </div>
            </FieldWrapper>

            {/* Info aggiuntive */}
            <div className="rndt-notice rndt-notice--info">
                <h4>{__('Sistemi di riferimento consigliati', 'rndt-manager')}</h4>
                <ul>
                    <li><strong>RDN2008 (EPSG:6706)</strong> - {__('Sistema geodetico nazionale italiano', 'rndt-manager')}</li>
                    <li><strong>ETRS89 (EPSG:4258)</strong> - {__('Sistema europeo', 'rndt-manager')}</li>
                    <li><strong>WGS84 (EPSG:4326)</strong> - {__('Sistema globale', 'rndt-manager')}</li>
                </ul>
            </div>
        </div>
    );
};

export default StepReferenceSystem;
