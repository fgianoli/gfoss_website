/**
 * Step Qualità
 *
 * @package RNDT_Manager
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextareaControl, TextControl, Button, CheckboxControl, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepQuality Component
 */
const StepQuality = ({ codelists, resourceType }) => {
    const { metadata, setField, addRepeatable, updateRepeatable, removeRepeatable, getFieldError } = useMetadata();

    const [newConformity, setNewConformity] = useState({
        specification_title: '',
        specification_date: '',
        specification_date_type: 'publication',
        explanation: '',
        pass: false,
    });

    // Preset specifiche INSPIRE
    const inspireSpecs = [
        {
            value: 'metadata',
            label: 'INSPIRE Metadata',
            title: 'REGOLAMENTO (CE) N. 1205/2008 - Metadati',
            date: '2008-12-04',
        },
        {
            value: 'interop',
            label: 'INSPIRE Interoperability',
            title: 'REGOLAMENTO (UE) N. 1089/2010 - Interoperabilità',
            date: '2010-12-08',
        },
        {
            value: 'services',
            label: 'INSPIRE Network Services',
            title: 'REGOLAMENTO (CE) N. 976/2009 - Servizi di rete',
            date: '2009-10-20',
        },
    ];

    const conformities = metadata.conformity || [];

    const handleAddConformity = () => {
        if (newConformity.specification_title) {
            addRepeatable('conformity', { ...newConformity });
            setNewConformity({
                specification_title: '',
                specification_date: '',
                specification_date_type: 'publication',
                explanation: '',
                pass: false,
            });
        }
    };

    const handlePresetSpec = (preset) => {
        const spec = inspireSpecs.find(s => s.value === preset);
        if (spec) {
            setNewConformity({
                ...newConformity,
                specification_title: spec.title,
                specification_date: spec.date,
            });
        }
    };

    return (
        <div className="rndt-step rndt-step--quality">
            <h3>{__('Qualità dei dati', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Informazioni sulla qualità, conformità e genealogia dei dati.', 'rndt-manager')}
            </p>

            {/* Genealogia (Lineage) */}
            <FieldWrapper
                label={__('Genealogia (Lineage)', 'rndt-manager')}
                required={resourceType === 'dataset' || resourceType === 'series'}
                error={getFieldError('lineage')}
                help={__('Descrizione della storia e/o della qualità complessiva della risorsa.', 'rndt-manager')}
            >
                <TextareaControl
                    value={metadata.lineage || ''}
                    onChange={(value) => setField('lineage', value)}
                    rows={4}
                    placeholder={__('Descrivi la provenienza e i processi di elaborazione dei dati...', 'rndt-manager')}
                />
            </FieldWrapper>

            {/* Risoluzione spaziale */}
            {resourceType !== 'service' && (
                <>
                    <h4>{__('Risoluzione spaziale', 'rndt-manager')}</h4>
                    <p className="rndt-step__help">
                        {__('Specifica la scala equivalente O la distanza (non entrambe).', 'rndt-manager')}
                    </p>

                    <div className="rndt-resolution-inputs">
                        <FieldWrapper
                            label={__('Scala equivalente (denominatore)', 'rndt-manager')}
                            help={__('Es: 10000 per scala 1:10.000', 'rndt-manager')}
                        >
                            <TextControl
                                type="number"
                                min="1"
                                value={metadata.equivalent_scale || ''}
                                onChange={(value) => setField('equivalent_scale', value)}
                                placeholder="10000"
                            />
                        </FieldWrapper>

                        <span className="rndt-resolution-separator">{__('oppure', 'rndt-manager')}</span>

                        <FieldWrapper
                            label={__('Distanza (metri)', 'rndt-manager')}
                        >
                            <TextControl
                                type="number"
                                step="0.01"
                                min="0"
                                value={metadata.distance_value || ''}
                                onChange={(value) => setField('distance_value', value)}
                                placeholder="0.5"
                            />
                        </FieldWrapper>

                        <FieldWrapper
                            label={__('Unità di misura', 'rndt-manager')}
                        >
                            <SelectControl
                                value={metadata.distance_uom || 'm'}
                                options={[
                                    { value: 'm', label: 'Metri' },
                                    { value: 'km', label: 'Chilometri' },
                                    { value: 'deg', label: 'Gradi' },
                                ]}
                                onChange={(value) => setField('distance_uom', value)}
                            />
                        </FieldWrapper>
                    </div>
                </>
            )}

            {/* Conformità */}
            <h4>{__('Dichiarazioni di conformità', 'rndt-manager')}</h4>
            <FieldWrapper
                label={__('Conformità INSPIRE', 'rndt-manager')}
                error={getFieldError('conformity')}
                help={__('Dichiarazione di conformità alle specifiche tecniche.', 'rndt-manager')}
            >
                {/* Lista conformità esistenti */}
                <div className="rndt-conformity-list">
                    {conformities.map((conf, index) => (
                        <div key={index} className="rndt-conformity-item">
                            <div className="rndt-conformity-item__content">
                                <strong>{conf.specification_title}</strong>
                                <span className="rndt-conformity-item__date">
                                    ({conf.specification_date})
                                </span>
                                <span className={`rndt-conformity-item__pass ${conf.pass ? 'is-pass' : 'is-fail'}`}>
                                    {conf.pass ? __('Conforme', 'rndt-manager') : __('Non conforme', 'rndt-manager')}
                                </span>
                            </div>
                            <Button
                                icon="no-alt"
                                isSmall
                                isDestructive
                                onClick={() => removeRepeatable('conformity', index)}
                            />
                        </div>
                    ))}
                </div>

                {/* Aggiungi nuova conformità */}
                <div className="rndt-conformity-add">
                    <SelectControl
                        label={__('Preset specifica', 'rndt-manager')}
                        value=""
                        options={[
                            { value: '', label: __('-- Seleziona preset --', 'rndt-manager') },
                            ...inspireSpecs.map(s => ({ value: s.value, label: s.label })),
                        ]}
                        onChange={handlePresetSpec}
                    />

                    <TextControl
                        label={__('Titolo specifica', 'rndt-manager')}
                        value={newConformity.specification_title}
                        onChange={(value) => setNewConformity({ ...newConformity, specification_title: value })}
                    />

                    <TextControl
                        label={__('Data specifica', 'rndt-manager')}
                        type="date"
                        value={newConformity.specification_date}
                        onChange={(value) => setNewConformity({ ...newConformity, specification_date: value })}
                    />

                    <TextControl
                        label={__('Spiegazione', 'rndt-manager')}
                        value={newConformity.explanation}
                        onChange={(value) => setNewConformity({ ...newConformity, explanation: value })}
                    />

                    <CheckboxControl
                        label={__('Conforme', 'rndt-manager')}
                        checked={newConformity.pass}
                        onChange={(value) => setNewConformity({ ...newConformity, pass: value })}
                    />

                    <Button
                        variant="secondary"
                        onClick={handleAddConformity}
                        disabled={!newConformity.specification_title}
                    >
                        {__('Aggiungi conformità', 'rndt-manager')}
                    </Button>
                </div>
            </FieldWrapper>
        </div>
    );
};

export default StepQuality;
