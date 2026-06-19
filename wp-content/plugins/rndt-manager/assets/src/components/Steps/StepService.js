/**
 * Step Servizio
 *
 * @package RNDT_Manager
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl, Button } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepService Component
 */
const StepService = ({ codelists }) => {
    const { metadata, setField, addRepeatable, removeRepeatable, getFieldError } = useMetadata();

    const [newOperation, setNewOperation] = useState({
        operation_name: '',
        dcp: 'WebServices',
        connect_point: '',
    });

    const [newCoupledResource, setNewCoupledResource] = useState({
        identifier: '',
        title: '',
    });

    // Tipi di servizio
    const serviceTypeOptions = codelists?.service_types || [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'discovery', label: __('Discovery (CSW)', 'rndt-manager') },
        { value: 'view', label: __('View (WMS/WMTS)', 'rndt-manager') },
        { value: 'download', label: __('Download (WFS/WCS/ATOM)', 'rndt-manager') },
        { value: 'transformation', label: __('Transformation', 'rndt-manager') },
        { value: 'invoke', label: __('Invoke (WPS)', 'rndt-manager') },
        { value: 'other', label: __('Altro', 'rndt-manager') },
    ];

    // Tipi coupling
    const couplingTypeOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'tight', label: __('Tight (stretto)', 'rndt-manager') },
        { value: 'mixed', label: __('Mixed (misto)', 'rndt-manager') },
        { value: 'loose', label: __('Loose (lasco)', 'rndt-manager') },
    ];

    // DCP options
    const dcpOptions = [
        { value: 'WebServices', label: 'Web Services' },
        { value: 'XML', label: 'XML' },
        { value: 'CORBA', label: 'CORBA' },
        { value: 'JAVA', label: 'JAVA' },
        { value: 'COM', label: 'COM' },
        { value: 'SQL', label: 'SQL' },
    ];

    // Operazioni predefinite per tipo servizio
    const operationPresets = {
        view: ['GetCapabilities', 'GetMap', 'GetFeatureInfo', 'GetTile'],
        download: ['GetCapabilities', 'GetFeature', 'DescribeFeatureType', 'GetCoverage'],
        discovery: ['GetCapabilities', 'GetRecords', 'GetRecordById', 'DescribeRecord'],
    };

    const operations = metadata.service_operations || [];
    const coupledResources = metadata.coupled_resources || [];

    // Aggiungi operazione
    const handleAddOperation = () => {
        if (newOperation.operation_name) {
            addRepeatable('service_operations', { ...newOperation });
            setNewOperation({
                operation_name: '',
                dcp: 'WebServices',
                connect_point: '',
            });
        }
    };

    // Aggiungi operazioni predefinite
    const handleAddPresetOperations = () => {
        const serviceType = metadata.service_type;
        const presets = operationPresets[serviceType];
        if (presets) {
            presets.forEach(opName => {
                if (!operations.find(o => o.operation_name === opName)) {
                    addRepeatable('service_operations', {
                        operation_name: opName,
                        dcp: 'WebServices',
                        connect_point: '',
                    });
                }
            });
        }
    };

    // Aggiungi risorsa accoppiata
    const handleAddCoupledResource = () => {
        if (newCoupledResource.identifier) {
            addRepeatable('coupled_resources', { ...newCoupledResource });
            setNewCoupledResource({ identifier: '', title: '' });
        }
    };

    return (
        <div className="rndt-step rndt-step--service">
            <h3>{__('Dettagli servizio', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Informazioni specifiche per servizi OGC (ISO 19119).', 'rndt-manager')}
            </p>

            {/* Tipo servizio */}
            <FieldWrapper
                label={__('Tipo di servizio', 'rndt-manager')}
                required={true}
                error={getFieldError('service_type')}
            >
                <SelectControl
                    value={metadata.service_type || ''}
                    options={serviceTypeOptions}
                    onChange={(value) => setField('service_type', value)}
                />
            </FieldWrapper>

            {/* Versione servizio */}
            <FieldWrapper
                label={__('Versione del servizio', 'rndt-manager')}
                help={__('Es: 1.3.0 per WMS, 2.0.0 per WFS.', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.service_type_version || ''}
                    onChange={(value) => setField('service_type_version', value)}
                    placeholder="1.3.0"
                />
            </FieldWrapper>

            {/* Tipo coupling */}
            <FieldWrapper
                label={__('Tipo di coupling', 'rndt-manager')}
                required={true}
                error={getFieldError('coupling_type')}
                help={__('Tight: il servizio opera solo su dati specifici. Loose: opera su qualsiasi dato.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.coupling_type || ''}
                    options={couplingTypeOptions}
                    onChange={(value) => setField('coupling_type', value)}
                />
            </FieldWrapper>

            {/* Operazioni del servizio */}
            <h4>{__('Operazioni del servizio', 'rndt-manager')}</h4>
            <FieldWrapper
                label={__('Operazioni supportate', 'rndt-manager')}
                required={true}
                error={getFieldError('service_operations')}
            >
                {/* Lista operazioni */}
                <div className="rndt-operations-list">
                    {operations.map((op, index) => (
                        <div key={index} className="rndt-operations-item">
                            <span className="rndt-operations-item__name">{op.operation_name}</span>
                            <span className="rndt-operations-item__dcp">{op.dcp}</span>
                            {op.connect_point && (
                                <a href={op.connect_point} target="_blank" rel="noopener noreferrer">
                                    {__('URL', 'rndt-manager')}
                                </a>
                            )}
                            <Button
                                icon="no-alt"
                                isSmall
                                isDestructive
                                onClick={() => removeRepeatable('service_operations', index)}
                            />
                        </div>
                    ))}
                </div>

                {/* Aggiungi preset */}
                {metadata.service_type && operationPresets[metadata.service_type] && (
                    <Button
                        variant="secondary"
                        onClick={handleAddPresetOperations}
                        style={{ marginBottom: '10px' }}
                    >
                        {__('Aggiungi operazioni standard', 'rndt-manager')}
                    </Button>
                )}

                {/* Aggiungi operazione manuale */}
                <div className="rndt-operations-add">
                    <TextControl
                        label={__('Nome operazione', 'rndt-manager')}
                        value={newOperation.operation_name}
                        onChange={(value) => setNewOperation({ ...newOperation, operation_name: value })}
                        placeholder="GetCapabilities"
                    />
                    <SelectControl
                        label={__('DCP', 'rndt-manager')}
                        value={newOperation.dcp}
                        options={dcpOptions}
                        onChange={(value) => setNewOperation({ ...newOperation, dcp: value })}
                    />
                    <TextControl
                        label={__('URL endpoint', 'rndt-manager')}
                        type="url"
                        value={newOperation.connect_point}
                        onChange={(value) => setNewOperation({ ...newOperation, connect_point: value })}
                        placeholder="https://..."
                    />
                    <Button
                        variant="secondary"
                        onClick={handleAddOperation}
                        disabled={!newOperation.operation_name}
                    >
                        {__('Aggiungi', 'rndt-manager')}
                    </Button>
                </div>
            </FieldWrapper>

            {/* Risorse accoppiate */}
            {(metadata.coupling_type === 'tight' || metadata.coupling_type === 'mixed') && (
                <>
                    <h4>{__('Risorse accoppiate', 'rndt-manager')}</h4>
                    <FieldWrapper
                        label={__('Dataset serviti', 'rndt-manager')}
                        required={metadata.coupling_type === 'tight'}
                        error={getFieldError('coupled_resources')}
                        help={__('Identificativi dei dataset su cui opera il servizio.', 'rndt-manager')}
                    >
                        {/* Lista risorse */}
                        <div className="rndt-coupled-list">
                            {coupledResources.map((res, index) => (
                                <div key={index} className="rndt-coupled-item">
                                    <span>{res.title || res.identifier}</span>
                                    <Button
                                        icon="no-alt"
                                        isSmall
                                        isDestructive
                                        onClick={() => removeRepeatable('coupled_resources', index)}
                                    />
                                </div>
                            ))}
                        </div>

                        {/* Aggiungi risorsa */}
                        <div className="rndt-coupled-add">
                            <TextControl
                                label={__('Identificativo (UUID o URL)', 'rndt-manager')}
                                value={newCoupledResource.identifier}
                                onChange={(value) => setNewCoupledResource({ ...newCoupledResource, identifier: value })}
                            />
                            <TextControl
                                label={__('Titolo', 'rndt-manager')}
                                value={newCoupledResource.title}
                                onChange={(value) => setNewCoupledResource({ ...newCoupledResource, title: value })}
                            />
                            <Button
                                variant="secondary"
                                onClick={handleAddCoupledResource}
                                disabled={!newCoupledResource.identifier}
                            >
                                {__('Aggiungi', 'rndt-manager')}
                            </Button>
                        </div>
                    </FieldWrapper>
                </>
            )}
        </div>
    );
};

export default StepService;
