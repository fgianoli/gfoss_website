/**
 * Wizard Component
 *
 * @package RNDT_Manager
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import WizardStepper from './WizardStepper';
import WizardActions from './WizardActions';
import GeoServerLayerModal from '../Modals/GeoServerLayerModal';
import ImportXmlModal from '../Modals/ImportXmlModal';
import { useMetadata } from '../../context/MetadataContext';

// Steps configuration
import StepIdentification from '../Steps/StepIdentification';
import StepClassification from '../Steps/StepClassification';
import StepGeographicExtent from '../Steps/StepGeographicExtent';
import StepTemporal from '../Steps/StepTemporal';
import StepQuality from '../Steps/StepQuality';
import StepConstraints from '../Steps/StepConstraints';
import StepDistribution from '../Steps/StepDistribution';
import StepResponsibleParty from '../Steps/StepResponsibleParty';
import StepReferenceSystem from '../Steps/StepReferenceSystem';
import StepMetadataInfo from '../Steps/StepMetadataInfo';
import StepService from '../Steps/StepService';

/**
 * Configurazione steps per tipo risorsa
 */
const getStepsConfig = (resourceType) => {
    const baseSteps = [
        {
            id: 'identification',
            title: __('Identificazione', 'rndt-manager'),
            component: StepIdentification,
            required: true,
        },
        {
            id: 'classification',
            title: __('Classificazione', 'rndt-manager'),
            component: StepClassification,
            required: true,
        },
        {
            id: 'geographic',
            title: __('Estensione geografica', 'rndt-manager'),
            component: StepGeographicExtent,
            required: true,
        },
        {
            id: 'temporal',
            title: __('Riferimento temporale', 'rndt-manager'),
            component: StepTemporal,
            required: true,
        },
        {
            id: 'quality',
            title: __('Qualità', 'rndt-manager'),
            component: StepQuality,
            required: resourceType !== 'service',
        },
        {
            id: 'constraints',
            title: __('Vincoli', 'rndt-manager'),
            component: StepConstraints,
            required: true,
        },
        {
            id: 'distribution',
            title: __('Distribuzione', 'rndt-manager'),
            component: StepDistribution,
            required: true,
        },
        {
            id: 'responsible',
            title: __('Parte responsabile', 'rndt-manager'),
            component: StepResponsibleParty,
            required: true,
        },
    ];

    // Sistema di riferimento (non per servizi)
    if (resourceType !== 'service') {
        baseSteps.push({
            id: 'reference_system',
            title: __('Sistema di riferimento', 'rndt-manager'),
            component: StepReferenceSystem,
            required: resourceType === 'dataset',
        });
    }

    // Info metadato (sempre)
    baseSteps.push({
        id: 'metadata_info',
        title: __('Info metadato', 'rndt-manager'),
        component: StepMetadataInfo,
        required: true,
    });

    // Dettagli servizio (solo per servizi)
    if (resourceType === 'service') {
        baseSteps.push({
            id: 'service',
            title: __('Dettagli servizio', 'rndt-manager'),
            component: StepService,
            required: true,
        });
    }

    return baseSteps;
};

/**
 * Wizard Component
 */
const Wizard = ({
    metadataId,
    resourceType,
    onSave,
    onValidate,
    onDownloadXml,
    onPreviewXml,
    onPublishCsw,
    onPublishGeoServer,
    onImportXml,
    onPreviewImport,
    fetchGeoServerLayers,
    codelists,
    i18n,
    settings,
}) => {
    const [currentStep, setCurrentStep] = useState(0);
    const [saving, setSaving] = useState(false);
    const [validating, setValidating] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [showXmlPreview, setShowXmlPreview] = useState(false);
    const [xmlPreviewContent, setXmlPreviewContent] = useState('');
    const [showValidationDetails, setShowValidationDetails] = useState(false);
    const [showGeoServerModal, setShowGeoServerModal] = useState(false);
    const [showImportModal, setShowImportModal] = useState(false);

    const { metadata, validation, isDirty, setValidation, setDirty } = useMetadata();

    // Steps per questo tipo di risorsa
    const steps = useMemo(() => getStepsConfig(resourceType), [resourceType]);

    // Step corrente
    const activeStep = steps[currentStep];
    const StepComponent = activeStep?.component;

    /**
     * Vai allo step precedente
     */
    const handlePrev = useCallback(() => {
        if (currentStep > 0) {
            setCurrentStep(currentStep - 1);
        }
    }, [currentStep]);

    /**
     * Vai allo step successivo
     */
    const handleNext = useCallback(() => {
        if (currentStep < steps.length - 1) {
            setCurrentStep(currentStep + 1);
        }
    }, [currentStep, steps.length]);

    /**
     * Vai a step specifico
     */
    const handleStepClick = useCallback((index) => {
        setCurrentStep(index);
    }, []);

    /**
     * Salva metadato
     */
    const handleSave = useCallback(async (options = {}) => {
        setSaving(true);
        try {
            await onSave(metadata, options);
            setDirty(false);
        } finally {
            setSaving(false);
        }
    }, [metadata, onSave, setDirty]);

    /**
     * Salva e valida (due step separati)
     */
    const handleSaveAndValidate = useCallback(async () => {
        // Step 1: Salva
        setSaving(true);
        let saveResult;
        try {
            saveResult = await onSave(metadata);
            setDirty(false);
        } catch (e) {
            setSaving(false);
            return;
        }
        setSaving(false);

        // Step 2: Valida (passa ID dal risultato del salvataggio per nuovi metadati)
        setValidating(true);
        try {
            const result = await onValidate(saveResult?.id);
            if (result) {
                setValidation(result);
                if (!result.valid) {
                    setShowValidationDetails(true);
                }
            }
        } finally {
            setValidating(false);
        }
    }, [metadata, onSave, onValidate, setValidation, setDirty]);

    /**
     * Solo valida
     */
    const handleValidate = useCallback(async () => {
        setValidating(true);
        try {
            const result = await onValidate();
            if (result) {
                setValidation(result);
                // Auto-espandi dettagli se ci sono errori
                if (!result.valid) {
                    setShowValidationDetails(true);
                }
            }
        } finally {
            setValidating(false);
        }
    }, [onValidate, setValidation]);

    /**
     * Scarica XML
     */
    const handleDownloadXml = useCallback(() => {
        if (onDownloadXml) {
            onDownloadXml();
        }
    }, [onDownloadXml]);

    /**
     * Anteprima XML
     */
    const handlePreviewXml = useCallback(async () => {
        try {
            const result = await onPreviewXml();
            if (result && result.xml) {
                setXmlPreviewContent(result.xml);
                setShowXmlPreview(true);
            }
        } catch (error) {
            console.error('Preview XML error:', error);
        }
    }, [onPreviewXml]);

    /**
     * Pubblica su CSW
     */
    const handlePublishCsw = useCallback(async () => {
        setPublishing(true);
        try {
            await onPublishCsw();
        } finally {
            setPublishing(false);
        }
    }, [onPublishCsw]);

    /**
     * Apri modal associazione layer GeoServer
     */
    const handleOpenGeoServerModal = useCallback(() => {
        setShowGeoServerModal(true);
    }, []);

    /**
     * Associa layer GeoServer
     */
    const handleAssociateLayer = useCallback(async (layerName) => {
        setPublishing(true);
        try {
            await onPublishGeoServer(layerName);
            setShowGeoServerModal(false);
        } finally {
            setPublishing(false);
        }
    }, [onPublishGeoServer]);

    /**
     * Apri modal import XML
     */
    const handleOpenImportModal = useCallback(() => {
        setShowImportModal(true);
    }, []);

    /**
     * Import XML
     */
    const handleImportXml = useCallback(async (xml, options) => {
        setImporting(true);
        try {
            await onImportXml(xml, options);
            setShowImportModal(false);
        } finally {
            setImporting(false);
        }
    }, [onImportXml]);

    /**
     * Chiudi anteprima XML
     */
    const handleCloseXmlPreview = useCallback(() => {
        setShowXmlPreview(false);
        setXmlPreviewContent('');
    }, []);

    /**
     * Ottieni errori per step corrente
     */
    const getStepErrors = useCallback((stepId) => {
        if (!validation.errors) return [];

        // Mappa campi validatore → step del wizard
        const stepFieldsMap = {
            identification: ['title', 'abstract', 'resource_identifier', 'resource_identifier_codespace', 'resource_language', 'parent_identifier', 'spatial_representation_type', 'equivalent_scale'],
            classification: ['topic_categories', 'keywords'],
            geographic: ['bbox', 'bbox_west', 'bbox_east', 'bbox_south', 'bbox_north'],
            temporal: ['dates', 'date_creation', 'date_publication', 'date_revision', 'temporal_extent', 'temporal_extent_begin', 'temporal_extent_end'],
            quality: ['lineage', 'conformity'],
            constraints: ['access_constraints', 'use_constraints', 'other_constraints'],
            distribution: ['distribution_formats', 'online_resources'],
            responsible: ['metadata_contact', 'resource_poc', 'email', 'url'],
            reference_system: ['reference_system_code'],
            metadata_info: ['file_identifier', 'metadata_language', 'metadata_date'],
            service: ['service_type', 'coupling_type', 'service_operations', 'coupled_resources'],
        };

        const stepFields = stepFieldsMap[stepId] || [];
        return validation.errors.filter(e => stepFields.includes(e.field));
    }, [validation.errors]);

    return (
        <div className="rndt-wizard">
            {/* Header */}
            <div className="rndt-wizard__header">
                <h2 className="rndt-wizard__title">
                    {metadataId
                        ? __('Modifica metadato', 'rndt-manager')
                        : __('Nuovo metadato', 'rndt-manager')
                    }
                    <span className="rndt-wizard__resource-type">
                        ({resourceType})
                    </span>
                </h2>

                {isDirty && (
                    <span className="rndt-wizard__dirty-indicator">
                        {__('Modifiche non salvate', 'rndt-manager')}
                    </span>
                )}
            </div>

            {/* Stepper */}
            <WizardStepper
                steps={steps}
                currentStep={currentStep}
                onStepClick={handleStepClick}
                getStepErrors={getStepErrors}
                validation={validation}
            />

            {/* Step Content */}
            <div className="rndt-wizard__content">
                {StepComponent && (
                    <StepComponent
                        codelists={codelists}
                        i18n={i18n}
                        resourceType={resourceType}
                        settings={settings}
                    />
                )}
            </div>

            {/* Actions */}
            <WizardActions
                currentStep={currentStep}
                totalSteps={steps.length}
                onPrev={handlePrev}
                onNext={handleNext}
                onSave={handleSave}
                onValidate={handleValidate}
                onSaveAndValidate={handleSaveAndValidate}
                onDownloadXml={handleDownloadXml}
                onPreviewXml={handlePreviewXml}
                onPublishCsw={handlePublishCsw}
                onPublishGeoServer={handleOpenGeoServerModal}
                onImportXml={handleOpenImportModal}
                saving={saving}
                validating={validating}
                publishing={publishing}
                isDirty={isDirty}
                isLastStep={currentStep === steps.length - 1}
                isValid={validation.valid === true}
                metadataId={metadataId}
                settings={settings}
            />

            {/* Validation Summary */}
            {validation.valid !== null && (
                <div className={`rndt-wizard__validation-summary ${validation.valid ? 'is-valid' : 'is-invalid'}`}>
                    <div
                        className="rndt-wizard__validation-summary-header"
                        onClick={() => !validation.valid && setShowValidationDetails(!showValidationDetails)}
                        style={!validation.valid ? { cursor: 'pointer', userSelect: 'none' } : {}}
                    >
                        {validation.valid ? (
                            <span className="dashicons dashicons-yes-alt" />
                        ) : (
                            <span className="dashicons dashicons-warning" />
                        )}
                        <span>
                            {validation.valid
                                ? __('Metadato valido', 'rndt-manager')
                                : `${validation.errors?.length || 0} ${__('errori', 'rndt-manager')}, ${validation.warnings?.length || 0} ${__('avvisi', 'rndt-manager')}`
                            }
                        </span>
                        {!validation.valid && (
                            <span
                                className="dashicons dashicons-arrow-down-alt2"
                                style={{
                                    marginLeft: 'auto',
                                    transform: showValidationDetails ? 'rotate(180deg)' : 'none',
                                    transition: 'transform 0.2s',
                                }}
                            />
                        )}
                    </div>

                    {showValidationDetails && !validation.valid && (
                        <div className="rndt-wizard__validation-details">
                            {validation.errors?.length > 0 && (
                                <div className="rndt-wizard__validation-errors">
                                    <h4>{__('Errori', 'rndt-manager')}</h4>
                                    <ul>
                                        {validation.errors.map((error, index) => {
                                            const stepIndex = steps.findIndex(s =>
                                                getStepErrors(s.id).some(e => e.field === error.field)
                                            );
                                            return (
                                                <li
                                                    key={index}
                                                    className="rndt-wizard__validation-item rndt-wizard__validation-item--error"
                                                    onClick={() => stepIndex >= 0 && handleStepClick(stepIndex)}
                                                    style={stepIndex >= 0 ? { cursor: 'pointer' } : {}}
                                                >
                                                    <span className="dashicons dashicons-dismiss" />
                                                    <strong>{error.field}:</strong> {error.message}
                                                    {stepIndex >= 0 && (
                                                        <span className="rndt-wizard__validation-step-link">
                                                            {' → '}{steps[stepIndex].title}
                                                        </span>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}
                            {validation.warnings?.length > 0 && (
                                <div className="rndt-wizard__validation-warnings">
                                    <h4>{__('Avvisi', 'rndt-manager')}</h4>
                                    <ul>
                                        {validation.warnings.map((warning, index) => (
                                            <li
                                                key={index}
                                                className="rndt-wizard__validation-item rndt-wizard__validation-item--warning"
                                            >
                                                <span className="dashicons dashicons-info-outline" />
                                                <strong>{warning.field}:</strong> {warning.message}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* GeoServer Layer Modal */}
            <GeoServerLayerModal
                isOpen={showGeoServerModal}
                onClose={() => setShowGeoServerModal(false)}
                onAssociate={handleAssociateLayer}
                fetchLayers={fetchGeoServerLayers}
                associating={publishing}
            />

            {/* Import XML Modal */}
            <ImportXmlModal
                isOpen={showImportModal}
                onClose={() => setShowImportModal(false)}
                onImport={handleImportXml}
                onPreview={onPreviewImport}
                importing={importing}
            />

            {/* XML Preview Modal */}
            {showXmlPreview && (
                <div className="rndt-xml-preview-modal active">
                    <div className="rndt-xml-preview-content">
                        <div className="rndt-xml-preview-header">
                            <h3>{__('Anteprima XML ISO 19139', 'rndt-manager')}</h3>
                            <button
                                className="rndt-xml-preview-close"
                                onClick={handleCloseXmlPreview}
                                aria-label={__('Chiudi', 'rndt-manager')}
                            >
                                &times;
                            </button>
                        </div>
                        <div className="rndt-xml-preview-body">
                            <pre>{xmlPreviewContent}</pre>
                        </div>
                        <div className="rndt-xml-preview-footer">
                            <button
                                className="button"
                                onClick={handleCloseXmlPreview}
                            >
                                {__('Chiudi', 'rndt-manager')}
                            </button>
                            <button
                                className="button button-primary"
                                onClick={handleDownloadXml}
                            >
                                {__('Scarica XML', 'rndt-manager')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Wizard;
