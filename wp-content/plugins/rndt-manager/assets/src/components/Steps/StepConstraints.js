/**
 * Step Vincoli
 *
 * @package RNDT_Manager
 */

import { __ } from '@wordpress/i18n';
import { SelectControl, TextareaControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepConstraints Component
 */
const StepConstraints = ({ codelists }) => {
    const { metadata, setField, getFieldError } = useMetadata();

    // Opzioni vincoli
    const restrictionOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'copyright', label: 'Copyright' },
        { value: 'patent', label: 'Patent' },
        { value: 'patentPending', label: 'Patent Pending' },
        { value: 'trademark', label: 'Trademark' },
        { value: 'license', label: 'License' },
        { value: 'intellectualPropertyRights', label: 'Intellectual Property Rights' },
        { value: 'restricted', label: 'Restricted' },
        { value: 'otherRestrictions', label: 'Other Restrictions' },
    ];

    const classificationOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'unclassified', label: __('Non classificato', 'rndt-manager') },
        { value: 'restricted', label: __('Riservato', 'rndt-manager') },
        { value: 'confidential', label: __('Confidenziale', 'rndt-manager') },
        { value: 'secret', label: __('Segreto', 'rndt-manager') },
        { value: 'topSecret', label: __('Top Secret', 'rndt-manager') },
    ];

    // Opzioni licenze comuni
    const licensePresets = [
        { value: '', label: __('-- Seleziona preset --', 'rndt-manager') },
        { value: 'cc0', label: 'CC0 - Public Domain', text: 'Licenza CC0 1.0 Universal (Public Domain Dedication)' },
        { value: 'ccby', label: 'CC BY 4.0', text: 'Licenza Creative Commons Attribuzione 4.0 Internazionale' },
        { value: 'ccbysa', label: 'CC BY-SA 4.0', text: 'Licenza Creative Commons Attribuzione - Condividi allo stesso modo 4.0 Internazionale' },
        { value: 'iodl', label: 'IODL 2.0', text: 'Italian Open Data License 2.0' },
    ];

    const handleLicensePreset = (preset) => {
        const selected = licensePresets.find(l => l.value === preset);
        if (selected && selected.text) {
            setField('other_constraints', selected.text);
        }
    };

    return (
        <div className="rndt-step rndt-step--constraints">
            <h3>{__('Vincoli', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Vincoli di accesso, uso e sicurezza applicati alla risorsa.', 'rndt-manager')}
            </p>

            {/* Vincoli legali */}
            <h4>{__('Vincoli legali', 'rndt-manager')}</h4>

            <FieldWrapper
                label={__('Vincoli di accesso', 'rndt-manager')}
                error={getFieldError('access_constraints')}
                help={__('Restrizioni sull\'accesso alla risorsa.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.access_constraints || ''}
                    options={restrictionOptions}
                    onChange={(value) => setField('access_constraints', value)}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Vincoli d\'uso', 'rndt-manager')}
                error={getFieldError('use_constraints')}
                help={__('Restrizioni sull\'uso della risorsa.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.use_constraints || ''}
                    options={restrictionOptions}
                    onChange={(value) => setField('use_constraints', value)}
                />
            </FieldWrapper>

            {/* Altri vincoli / Licenza */}
            <FieldWrapper
                label={__('Altri vincoli / Licenza', 'rndt-manager')}
                required={metadata.use_constraints === 'otherRestrictions'}
                error={getFieldError('other_constraints')}
                help={__('Obbligatorio se vincoli d\'uso = "Other Restrictions". Specifica la licenza o altri vincoli.', 'rndt-manager')}
            >
                <SelectControl
                    value=""
                    options={licensePresets}
                    onChange={handleLicensePreset}
                />
                <TextareaControl
                    value={metadata.other_constraints || ''}
                    onChange={(value) => setField('other_constraints', value)}
                    rows={3}
                    placeholder={__('Descrivi i vincoli o specifica la licenza...', 'rndt-manager')}
                />
            </FieldWrapper>

            {/* Limitazioni d'uso */}
            <FieldWrapper
                label={__('Limitazioni d\'uso', 'rndt-manager')}
                help={__('Limitazioni che riguardano l\'idoneità dei dati.', 'rndt-manager')}
            >
                <TextareaControl
                    value={metadata.use_limitation || ''}
                    onChange={(value) => setField('use_limitation', value)}
                    rows={2}
                    placeholder={__('Es: Non utilizzare per scopi di navigazione...', 'rndt-manager')}
                />
            </FieldWrapper>

            {/* Vincoli di sicurezza */}
            <h4>{__('Vincoli di sicurezza', 'rndt-manager')}</h4>

            <FieldWrapper
                label={__('Classificazione di sicurezza', 'rndt-manager')}
                help={__('Livello di classificazione della risorsa.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.classification || ''}
                    options={classificationOptions}
                    onChange={(value) => setField('classification', value)}
                />
            </FieldWrapper>
        </div>
    );
};

export default StepConstraints;
