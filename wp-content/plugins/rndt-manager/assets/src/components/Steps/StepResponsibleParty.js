/**
 * Step Parte Responsabile
 *
 * @package RNDT_Manager
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl, Button, Modal, CheckboxControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';
import useApi from '../../hooks/useApi';

const emptyParty = {
    role_type: 'metadata_contact',
    organisation_name: '',
    individual_name: '',
    position_name: '',
    role_code: 'pointOfContact',
    phone: '',
    fax: '',
    email: '',
    delivery_point: '',
    city: '',
    postal_code: '',
    country: 'Italia',
    url: '',
};

/**
 * StepResponsibleParty Component
 */
const StepResponsibleParty = ({ codelists }) => {
    const { metadata, addRepeatable, updateRepeatable, removeRepeatable, getFieldError } = useMetadata();
    const { fetchResponsiblePresets, createResponsiblePreset, deleteResponsiblePreset } = useApi();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState(null);
    const [currentParty, setCurrentParty] = useState({ ...emptyParty });

    // Presets state
    const [presets, setPresets] = useState([]);
    const [presetsLoaded, setPresetsLoaded] = useState(false);
    const [saveAsPreset, setSaveAsPreset] = useState(false);
    const [presetName, setPresetName] = useState('');
    const [savingPreset, setSavingPreset] = useState(false);

    // Carica presets al primo render
    useEffect(() => {
        if (!presetsLoaded) {
            loadPresets();
        }
    }, [presetsLoaded]);

    const loadPresets = useCallback(async () => {
        try {
            const result = await fetchResponsiblePresets();
            setPresets(Array.isArray(result) ? result : []);
        } catch (e) {
            // Silente: presets non sono critici
        }
        setPresetsLoaded(true);
    }, [fetchResponsiblePresets]);

    // Tipi di ruolo interno
    const roleTypes = [
        { value: 'metadata_contact', label: __('Contatto metadato', 'rndt-manager') },
        { value: 'resource_poc', label: __('Punto di contatto risorsa', 'rndt-manager') },
        { value: 'distributor', label: __('Distributore', 'rndt-manager') },
    ];

    // Codici ruolo ISO
    const roleCodes = codelists?.role_codes || [
        { value: 'resourceProvider', label: 'Resource Provider' },
        { value: 'custodian', label: 'Custodian' },
        { value: 'owner', label: 'Owner' },
        { value: 'user', label: 'User' },
        { value: 'distributor', label: 'Distributor' },
        { value: 'originator', label: 'Originator' },
        { value: 'pointOfContact', label: 'Point of Contact' },
        { value: 'principalInvestigator', label: 'Principal Investigator' },
        { value: 'processor', label: 'Processor' },
        { value: 'publisher', label: 'Publisher' },
        { value: 'author', label: 'Author' },
    ];

    const parties = metadata.responsible_parties || [];

    // Filtra per tipo
    const getPartiesByType = (type) => parties.filter(p => p.role_type === type);

    // Applica preset: compila i campi dal preset selezionato
    const handleApplyPreset = (presetId) => {
        if (!presetId) return;
        const preset = presets.find(p => String(p.id) === String(presetId));
        if (!preset) return;

        setCurrentParty({
            ...currentParty,
            organisation_name: preset.organisation_name || '',
            individual_name: preset.individual_name || '',
            position_name: preset.position_name || '',
            role_code: preset.role_code || 'pointOfContact',
            phone: preset.phone || '',
            fax: preset.fax || '',
            email: preset.email || '',
            delivery_point: preset.delivery_point || '',
            city: preset.city || '',
            postal_code: preset.postal_code || '',
            country: preset.country || 'Italia',
            url: preset.online_resource_url || '',
        });
    };

    // Elimina preset
    const handleDeletePreset = async (presetId) => {
        try {
            await deleteResponsiblePreset(presetId);
            setPresets(presets.filter(p => String(p.id) !== String(presetId)));
        } catch (e) {
            console.error('Errore eliminazione preset:', e);
        }
    };

    // Apri modal per nuovo contatto
    const handleAddNew = (roleType) => {
        setCurrentParty({
            ...emptyParty,
            role_type: roleType,
        });
        setEditingIndex(null);
        setSaveAsPreset(false);
        setPresetName('');
        setIsModalOpen(true);
    };

    // Apri modal per modifica
    const handleEdit = (index) => {
        setCurrentParty({ ...parties[index] });
        setEditingIndex(index);
        setSaveAsPreset(false);
        setPresetName('');
        setIsModalOpen(true);
    };

    // Salva contatto (e opzionalmente come preset)
    const handleSave = async () => {
        if (editingIndex !== null) {
            updateRepeatable('responsible_parties', editingIndex, currentParty);
        } else {
            addRepeatable('responsible_parties', currentParty);
        }

        // Salva come preset se richiesto
        if (saveAsPreset && presetName.trim()) {
            setSavingPreset(true);
            try {
                const result = await createResponsiblePreset({
                    preset_name: presetName.trim(),
                    organisation_name: currentParty.organisation_name,
                    individual_name: currentParty.individual_name,
                    position_name: currentParty.position_name,
                    role_code: currentParty.role_code,
                    phone: currentParty.phone,
                    fax: currentParty.fax,
                    email: currentParty.email,
                    delivery_point: currentParty.delivery_point,
                    city: currentParty.city,
                    postal_code: currentParty.postal_code,
                    country: currentParty.country,
                    url: currentParty.url,
                });
                if (result) {
                    setPresets([...presets, result]);
                }
            } catch (e) {
                console.error('Errore salvataggio preset:', e);
            }
            setSavingPreset(false);
        }

        setIsModalOpen(false);
    };

    // Renderizza lista contatti per tipo
    const renderPartiesList = (type, label, required) => {
        const typeParties = getPartiesByType(type);
        const globalIndex = (party) => parties.findIndex(p => p === party);

        return (
            <FieldWrapper
                label={label}
                required={required}
                error={getFieldError(type)}
            >
                <div className="rndt-parties-list">
                    {typeParties.length === 0 ? (
                        <p className="rndt-parties-empty">
                            {__('Nessun contatto inserito.', 'rndt-manager')}
                        </p>
                    ) : (
                        typeParties.map((party) => {
                            const idx = globalIndex(party);
                            return (
                                <div key={idx} className="rndt-parties-item">
                                    <div className="rndt-parties-item__content">
                                        <strong>{party.organisation_name}</strong>
                                        {party.individual_name && (
                                            <span> - {party.individual_name}</span>
                                        )}
                                        <br />
                                        <small>{party.email}</small>
                                    </div>
                                    <div className="rndt-parties-item__actions">
                                        <Button
                                            icon="edit"
                                            isSmall
                                            onClick={() => handleEdit(idx)}
                                        />
                                        <Button
                                            icon="no-alt"
                                            isSmall
                                            isDestructive
                                            onClick={() => removeRepeatable('responsible_parties', idx)}
                                        />
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
                <Button
                    variant="secondary"
                    onClick={() => handleAddNew(type)}
                >
                    {__('Aggiungi contatto', 'rndt-manager')}
                </Button>
            </FieldWrapper>
        );
    };

    return (
        <div className="rndt-step rndt-step--responsible">
            <h3>{__('Parte responsabile', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Organizzazioni e persone responsabili per il metadato e la risorsa.', 'rndt-manager')}
            </p>

            {/* Contatto metadato */}
            {renderPartiesList('metadata_contact', __('Contatto per il metadato', 'rndt-manager'), true)}

            {/* Punto di contatto risorsa */}
            {renderPartiesList('resource_poc', __('Punto di contatto per la risorsa', 'rndt-manager'), true)}

            {/* Distributore */}
            {renderPartiesList('distributor', __('Distributore', 'rndt-manager'), false)}

            {/* Modal per inserimento/modifica */}
            {isModalOpen && (
                <Modal
                    title={editingIndex !== null ? __('Modifica contatto', 'rndt-manager') : __('Nuovo contatto', 'rndt-manager')}
                    onRequestClose={() => setIsModalOpen(false)}
                    className="rndt-party-modal"
                >
                    <div className="rndt-party-form">
                        {/* Selezione preset */}
                        {presets.length > 0 && (
                            <div className="rndt-party-form__preset">
                                <SelectControl
                                    label={__('Carica da preset salvato', 'rndt-manager')}
                                    value=""
                                    options={[
                                        { value: '', label: __('— Seleziona un preset —', 'rndt-manager') },
                                        ...presets.map(p => ({
                                            value: String(p.id),
                                            label: p.preset_name,
                                        })),
                                    ]}
                                    onChange={handleApplyPreset}
                                />
                            </div>
                        )}

                        <SelectControl
                            label={__('Tipo contatto', 'rndt-manager')}
                            value={currentParty.role_type}
                            options={roleTypes}
                            onChange={(value) => setCurrentParty({ ...currentParty, role_type: value })}
                        />

                        <TextControl
                            label={__('Nome organizzazione', 'rndt-manager')}
                            value={currentParty.organisation_name}
                            onChange={(value) => setCurrentParty({ ...currentParty, organisation_name: value })}
                            required
                        />

                        <TextControl
                            label={__('Nome persona', 'rndt-manager')}
                            value={currentParty.individual_name}
                            onChange={(value) => setCurrentParty({ ...currentParty, individual_name: value })}
                        />

                        <TextControl
                            label={__('Posizione', 'rndt-manager')}
                            value={currentParty.position_name}
                            onChange={(value) => setCurrentParty({ ...currentParty, position_name: value })}
                        />

                        <SelectControl
                            label={__('Ruolo', 'rndt-manager')}
                            value={currentParty.role_code}
                            options={roleCodes}
                            onChange={(value) => setCurrentParty({ ...currentParty, role_code: value })}
                        />

                        <TextControl
                            label={__('Email', 'rndt-manager')}
                            type="email"
                            value={currentParty.email}
                            onChange={(value) => setCurrentParty({ ...currentParty, email: value })}
                            required
                        />

                        <TextControl
                            label={__('Telefono', 'rndt-manager')}
                            type="tel"
                            value={currentParty.phone}
                            onChange={(value) => setCurrentParty({ ...currentParty, phone: value })}
                        />

                        <TextControl
                            label={__('Indirizzo', 'rndt-manager')}
                            value={currentParty.delivery_point}
                            onChange={(value) => setCurrentParty({ ...currentParty, delivery_point: value })}
                        />

                        <TextControl
                            label={__('Città', 'rndt-manager')}
                            value={currentParty.city}
                            onChange={(value) => setCurrentParty({ ...currentParty, city: value })}
                        />

                        <TextControl
                            label={__('CAP', 'rndt-manager')}
                            value={currentParty.postal_code}
                            onChange={(value) => setCurrentParty({ ...currentParty, postal_code: value })}
                        />

                        <TextControl
                            label={__('Paese', 'rndt-manager')}
                            value={currentParty.country}
                            onChange={(value) => setCurrentParty({ ...currentParty, country: value })}
                        />

                        <TextControl
                            label={__('Sito web', 'rndt-manager')}
                            type="url"
                            value={currentParty.url}
                            onChange={(value) => setCurrentParty({ ...currentParty, url: value })}
                        />

                        {/* Salva come preset */}
                        <div className="rndt-party-form__save-preset">
                            <CheckboxControl
                                label={__('Salva come preset per uso futuro', 'rndt-manager')}
                                checked={saveAsPreset}
                                onChange={setSaveAsPreset}
                            />
                            {saveAsPreset && (
                                <TextControl
                                    label={__('Nome preset', 'rndt-manager')}
                                    value={presetName}
                                    onChange={setPresetName}
                                    placeholder={currentParty.organisation_name || __('Es: Comune di Roma', 'rndt-manager')}
                                />
                            )}
                        </div>

                        <div className="rndt-party-form__actions">
                            <Button variant="secondary" onClick={() => setIsModalOpen(false)}>
                                {__('Annulla', 'rndt-manager')}
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleSave}
                                disabled={!currentParty.organisation_name || !currentParty.email || savingPreset}
                                isBusy={savingPreset}
                            >
                                {__('Salva', 'rndt-manager')}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Gestione presets */}
            {presets.length > 0 && (
                <div className="rndt-presets-summary">
                    <h4>{__('Presets salvati', 'rndt-manager')}</h4>
                    <div className="rndt-presets-list">
                        {presets.map(preset => (
                            <div key={preset.id} className="rndt-presets-item">
                                <span className="rndt-presets-item__name">
                                    {preset.preset_name}
                                </span>
                                <small className="rndt-presets-item__org">
                                    {preset.organisation_name}
                                    {preset.email && ` — ${preset.email}`}
                                </small>
                                <Button
                                    icon="no-alt"
                                    isSmall
                                    isDestructive
                                    onClick={() => handleDeletePreset(preset.id)}
                                    label={__('Elimina preset', 'rndt-manager')}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default StepResponsibleParty;
