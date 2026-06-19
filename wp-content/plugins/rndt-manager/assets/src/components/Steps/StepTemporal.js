/**
 * Step Riferimento Temporale
 *
 * @package RNDT_Manager
 */

import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepTemporal Component
 */
const StepTemporal = ({ codelists }) => {
    const { metadata, setField, getFieldError } = useMetadata();

    const maintenanceOptions = [
        { value: '', label: __('-- Seleziona --', 'rndt-manager') },
        { value: 'continual', label: __('Continuo', 'rndt-manager') },
        { value: 'daily', label: __('Giornaliero', 'rndt-manager') },
        { value: 'weekly', label: __('Settimanale', 'rndt-manager') },
        { value: 'fortnightly', label: __('Quindicinale', 'rndt-manager') },
        { value: 'monthly', label: __('Mensile', 'rndt-manager') },
        { value: 'quarterly', label: __('Trimestrale', 'rndt-manager') },
        { value: 'biannually', label: __('Semestrale', 'rndt-manager') },
        { value: 'annually', label: __('Annuale', 'rndt-manager') },
        { value: 'asNeeded', label: __('Quando necessario', 'rndt-manager') },
        { value: 'irregular', label: __('Irregolare', 'rndt-manager') },
        { value: 'notPlanned', label: __('Non pianificato', 'rndt-manager') },
        { value: 'unknown', label: __('Sconosciuto', 'rndt-manager') },
    ];

    return (
        <div className="rndt-step rndt-step--temporal">
            <h3>{__('Riferimento temporale', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Date significative e estensione temporale della risorsa.', 'rndt-manager')}
            </p>

            <div className="rndt-notice rndt-notice--info">
                {__('Almeno una data tra creazione, pubblicazione e revisione è obbligatoria.', 'rndt-manager')}
            </div>

            {/* Date della risorsa - almeno una obbligatoria */}
            <FieldWrapper
                label={__('Data di creazione', 'rndt-manager')}
                error={getFieldError('date_creation') || getFieldError('dates')}
                help={__('Data in cui la risorsa è stata creata.', 'rndt-manager')}
            >
                <TextControl
                    type="date"
                    value={metadata.date_creation || ''}
                    onChange={(value) => setField('date_creation', value)}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Data di pubblicazione', 'rndt-manager')}
                error={getFieldError('date_publication')}
                help={__('Data in cui la risorsa è stata pubblicata.', 'rndt-manager')}
            >
                <TextControl
                    type="date"
                    value={metadata.date_publication || ''}
                    onChange={(value) => setField('date_publication', value)}
                />
            </FieldWrapper>

            <FieldWrapper
                label={__('Data di revisione', 'rndt-manager')}
                error={getFieldError('date_revision')}
                help={__('Data dell\'ultimo aggiornamento.', 'rndt-manager')}
            >
                <TextControl
                    type="date"
                    value={metadata.date_revision || ''}
                    onChange={(value) => setField('date_revision', value)}
                />
            </FieldWrapper>

            {/* Estensione temporale */}
            <h4>{__('Estensione temporale', 'rndt-manager')}</h4>
            <p className="rndt-step__help">
                {__('Periodo di tempo coperto dai dati (opzionale).', 'rndt-manager')}
            </p>

            <div className="rndt-temporal-extent">
                <FieldWrapper
                    label={__('Data inizio', 'rndt-manager')}
                    error={getFieldError('temporal_extent')}
                >
                    <TextControl
                        type="date"
                        value={metadata.temporal_extent_begin || ''}
                        onChange={(value) => setField('temporal_extent_begin', value)}
                    />
                </FieldWrapper>

                <FieldWrapper
                    label={__('Data fine', 'rndt-manager')}
                >
                    <TextControl
                        type="date"
                        value={metadata.temporal_extent_end || ''}
                        onChange={(value) => setField('temporal_extent_end', value)}
                    />
                </FieldWrapper>
            </div>

            {/* Frequenza aggiornamento */}
            <FieldWrapper
                label={__('Frequenza di aggiornamento', 'rndt-manager')}
                help={__('Con quale frequenza la risorsa viene aggiornata.', 'rndt-manager')}
            >
                <SelectControl
                    value={metadata.maintenance_frequency || ''}
                    options={maintenanceOptions}
                    onChange={(value) => setField('maintenance_frequency', value)}
                />
            </FieldWrapper>
        </div>
    );
};

export default StepTemporal;
