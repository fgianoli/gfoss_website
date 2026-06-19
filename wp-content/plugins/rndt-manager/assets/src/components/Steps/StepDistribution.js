/**
 * Step Distribuzione
 *
 * @package RNDT_Manager
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl, Button } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepDistribution Component
 */
const StepDistribution = ({ codelists }) => {
    const { metadata, addRepeatable, removeRepeatable, getFieldError } = useMetadata();

    // Stato per nuovi elementi
    const [newFormat, setNewFormat] = useState({ name: '', version: '', specification: '' });
    const [newResource, setNewResource] = useState({
        url: '',
        protocol: '',
        name: '',
        description: '',
        function: '',
    });

    // Protocolli comuni
    const protocolOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'OGC:WMS', label: 'OGC Web Map Service (WMS)' },
        { value: 'OGC:WFS', label: 'OGC Web Feature Service (WFS)' },
        { value: 'OGC:WCS', label: 'OGC Web Coverage Service (WCS)' },
        { value: 'OGC:WMTS', label: 'OGC Web Map Tile Service (WMTS)' },
        { value: 'OGC:CSW', label: 'OGC Catalogue Service (CSW)' },
        { value: 'OGC:SOS', label: 'OGC Sensor Observation Service (SOS)' },
        { value: 'OGC:WPS', label: 'OGC Web Processing Service (WPS)' },
        { value: 'INSPIRE:ATOM', label: 'ATOM Feed' },
        { value: 'WWW:LINK', label: 'Web Link' },
        { value: 'WWW:DOWNLOAD', label: 'File Download' },
        { value: 'FILE:GEO', label: 'Geographic File' },
    ];

    // Funzioni online resource
    const functionOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'download', label: __('Download', 'rndt-manager') },
        { value: 'information', label: __('Informazioni', 'rndt-manager') },
        { value: 'offlineAccess', label: __('Accesso offline', 'rndt-manager') },
        { value: 'order', label: __('Ordine', 'rndt-manager') },
        { value: 'search', label: __('Ricerca', 'rndt-manager') },
    ];

    // Formati comuni - label serve per il SelectControl, name/version per compilare il form
    const formatPresets = [
        { value: '', label: __('-- Seleziona preset --', 'rndt-manager') },
        { value: 'shp', label: 'ESRI Shapefile (1.0)', name: 'ESRI Shapefile', version: '1.0' },
        { value: 'geojson', label: 'GeoJSON (1.0)', name: 'GeoJSON', version: '1.0' },
        { value: 'gml', label: 'GML (3.2.1)', name: 'GML', version: '3.2.1' },
        { value: 'gpkg', label: 'GeoPackage (1.3)', name: 'GeoPackage', version: '1.3' },
        { value: 'geotiff', label: 'GeoTIFF (1.0)', name: 'GeoTIFF', version: '1.0' },
        { value: 'csv', label: 'CSV', name: 'CSV', version: '' },
        { value: 'kml', label: 'KML (2.3)', name: 'KML', version: '2.3' },
    ];

    const formats = metadata.distribution_formats || [];
    const onlineResources = metadata.online_resources || [];

    // Handlers formati
    const handleFormatPreset = (preset) => {
        const selected = formatPresets.find(f => f.value === preset);
        if (selected) {
            setNewFormat({ name: selected.name, version: selected.version, specification: '' });
        }
    };

    const handleAddFormat = () => {
        if (newFormat.name) {
            addRepeatable('distribution_formats', { ...newFormat });
            setNewFormat({ name: '', version: '', specification: '' });
        }
    };

    // Handlers risorse online
    const handleAddResource = () => {
        if (newResource.url) {
            addRepeatable('online_resources', { ...newResource });
            setNewResource({
                url: '',
                protocol: '',
                name: '',
                description: '',
                function: '',
            });
        }
    };

    return (
        <div className="rndt-step rndt-step--distribution">
            <h3>{__('Distribuzione', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Formati di distribuzione e risorse online per accedere ai dati.', 'rndt-manager')}
            </p>

            {/* Formati di distribuzione */}
            <h4>{__('Formati di distribuzione', 'rndt-manager')}</h4>
            <FieldWrapper
                label={__('Formati', 'rndt-manager')}
                error={getFieldError('distribution_formats')}
            >
                {/* Lista formati */}
                <div className="rndt-formats-list">
                    {formats.map((format, index) => (
                        <div key={index} className="rndt-formats-item">
                            <span>{format.name}</span>
                            {format.version && <span className="rndt-formats-item__version">v{format.version}</span>}
                            <Button
                                icon="no-alt"
                                isSmall
                                isDestructive
                                onClick={() => removeRepeatable('distribution_formats', index)}
                            />
                        </div>
                    ))}
                </div>

                {/* Aggiungi formato */}
                <div className="rndt-formats-add">
                    <SelectControl
                        value=""
                        options={formatPresets}
                        onChange={handleFormatPreset}
                    />
                    <TextControl
                        placeholder={__('Nome formato', 'rndt-manager')}
                        value={newFormat.name}
                        onChange={(value) => setNewFormat({ ...newFormat, name: value })}
                    />
                    <TextControl
                        placeholder={__('Versione', 'rndt-manager')}
                        value={newFormat.version}
                        onChange={(value) => setNewFormat({ ...newFormat, version: value })}
                    />
                    <Button
                        variant="secondary"
                        onClick={handleAddFormat}
                        disabled={!newFormat.name}
                    >
                        {__('Aggiungi', 'rndt-manager')}
                    </Button>
                </div>
            </FieldWrapper>

            {/* Risorse online */}
            <h4>{__('Risorse online', 'rndt-manager')}</h4>
            <FieldWrapper
                label={__('URL e servizi', 'rndt-manager')}
                error={getFieldError('online_resources')}
                help={__('Link per accedere alla risorsa.', 'rndt-manager')}
            >
                {/* Lista risorse */}
                <div className="rndt-resources-list">
                    {onlineResources.map((res, index) => (
                        <div key={index} className="rndt-resources-item">
                            <div className="rndt-resources-item__content">
                                <a href={res.url} target="_blank" rel="noopener noreferrer">
                                    {res.name || res.url}
                                </a>
                                {res.protocol && (
                                    <span className="rndt-resources-item__protocol">{res.protocol}</span>
                                )}
                            </div>
                            <Button
                                icon="no-alt"
                                isSmall
                                isDestructive
                                onClick={() => removeRepeatable('online_resources', index)}
                            />
                        </div>
                    ))}
                </div>

                {/* Aggiungi risorsa */}
                <div className="rndt-resources-add">
                    <TextControl
                        label={__('URL', 'rndt-manager')}
                        type="url"
                        value={newResource.url}
                        onChange={(value) => setNewResource({ ...newResource, url: value })}
                        placeholder="https://..."
                    />
                    <SelectControl
                        label={__('Protocollo', 'rndt-manager')}
                        value={newResource.protocol}
                        options={protocolOptions}
                        onChange={(value) => setNewResource({ ...newResource, protocol: value })}
                    />
                    <TextControl
                        label={__('Nome', 'rndt-manager')}
                        value={newResource.name}
                        onChange={(value) => setNewResource({ ...newResource, name: value })}
                    />
                    <TextControl
                        label={__('Descrizione', 'rndt-manager')}
                        value={newResource.description}
                        onChange={(value) => setNewResource({ ...newResource, description: value })}
                    />
                    <SelectControl
                        label={__('Funzione', 'rndt-manager')}
                        value={newResource.function}
                        options={functionOptions}
                        onChange={(value) => setNewResource({ ...newResource, function: value })}
                    />
                    <Button
                        variant="secondary"
                        onClick={handleAddResource}
                        disabled={!newResource.url}
                    >
                        {__('Aggiungi risorsa', 'rndt-manager')}
                    </Button>
                </div>
            </FieldWrapper>
        </div>
    );
};

export default StepDistribution;
