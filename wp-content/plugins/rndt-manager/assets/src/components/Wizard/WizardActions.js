/**
 * Wizard Actions Component
 *
 * @package RNDT_Manager
 */

import { Button, Spinner, DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreVertical, download, upload } from '@wordpress/icons';

/**
 * WizardActions Component
 */
const WizardActions = ({
    currentStep,
    totalSteps,
    onPrev,
    onNext,
    onSave,
    onValidate,
    onSaveAndValidate,
    onDownloadXml,
    onPreviewXml,
    onPublishCsw,
    onPublishGeoServer,
    onImportXml,
    saving,
    validating,
    publishing,
    isDirty,
    isLastStep,
    isValid,
    metadataId,
    settings,
}) => {
    // Verifica se CSW è configurato e abilitato
    const cswConfigured = settings?.csw?.enabled && settings?.csw?.url;
    // Verifica se GeoServer è configurato e abilitato
    const geoserverConfigured = settings?.geoserver?.enabled && settings?.geoserver?.url;

    return (
        <div className="rndt-wizard__actions">
            <div className="rndt-wizard__actions-left">
                <Button
                    variant="secondary"
                    onClick={onPrev}
                    disabled={currentStep === 0 || saving}
                >
                    {__('Precedente', 'rndt-manager')}
                </Button>
            </div>

            <div className="rndt-wizard__actions-center">
                <span className="rndt-wizard__step-indicator">
                    {__('Step', 'rndt-manager')} {currentStep + 1} {__('di', 'rndt-manager')} {totalSteps}
                </span>
            </div>

            <div className="rndt-wizard__actions-right">
                {/* Salva bozza */}
                <Button
                    variant="secondary"
                    onClick={() => onSave()}
                    disabled={saving || !isDirty}
                >
                    {saving ? (
                        <>
                            <Spinner />
                            {__('Salvataggio...', 'rndt-manager')}
                        </>
                    ) : (
                        __('Salva bozza', 'rndt-manager')
                    )}
                </Button>

                {/* Valida (solo se già salvato) */}
                <Button
                    variant="secondary"
                    onClick={onValidate}
                    disabled={validating || isDirty || !metadataId}
                >
                    {validating ? (
                        <>
                            <Spinner />
                            {__('Validazione...', 'rndt-manager')}
                        </>
                    ) : (
                        __('Valida', 'rndt-manager')
                    )}
                </Button>

                {/* Prossimo o Salva e Valida */}
                {isLastStep ? (
                    <Button
                        variant="primary"
                        onClick={onSaveAndValidate}
                        disabled={saving || validating}
                    >
                        {saving || validating ? (
                            <>
                                <Spinner />
                                {__('Elaborazione...', 'rndt-manager')}
                            </>
                        ) : (
                            __('Salva e Valida', 'rndt-manager')
                        )}
                    </Button>
                ) : (
                    <Button
                        variant="primary"
                        onClick={onNext}
                        disabled={saving}
                    >
                        {__('Successivo', 'rndt-manager')}
                    </Button>
                )}

                {/* Scarica XML - bottone visibile sull'ultimo step quando il metadato è salvato */}
                {isLastStep && metadataId && (
                    <Button
                        variant="secondary"
                        icon={download}
                        onClick={onDownloadXml}
                        disabled={saving || isDirty}
                    >
                        {__('Scarica XML', 'rndt-manager')}
                    </Button>
                )}

                {/* Menu azioni aggiuntive (solo se salvato) */}
                <DropdownMenu
                    icon={moreVertical}
                    label={__('Altre azioni', 'rndt-manager')}
                    className="rndt-wizard__more-actions"
                >
                    {({ onClose }) => (
                        <>
                            {metadataId && (
                                <MenuGroup label={__('Esporta', 'rndt-manager')}>
                                    <MenuItem
                                        onClick={() => {
                                            onPreviewXml();
                                            onClose();
                                        }}
                                    >
                                        {__('Anteprima XML', 'rndt-manager')}
                                    </MenuItem>
                                    <MenuItem
                                        icon={download}
                                        onClick={() => {
                                            onDownloadXml();
                                            onClose();
                                        }}
                                    >
                                        {__('Scarica XML', 'rndt-manager')}
                                    </MenuItem>
                                </MenuGroup>
                            )}

                            <MenuGroup label={__('Importa', 'rndt-manager')}>
                                <MenuItem
                                    icon={upload}
                                    onClick={() => {
                                        onImportXml();
                                        onClose();
                                    }}
                                >
                                    {__('Importa XML', 'rndt-manager')}
                                </MenuItem>
                            </MenuGroup>

                            {metadataId && (cswConfigured || geoserverConfigured) && (
                                <MenuGroup label={__('Pubblica', 'rndt-manager')}>
                                    {cswConfigured && (
                                        <MenuItem
                                            icon={upload}
                                            onClick={() => {
                                                onPublishCsw();
                                                onClose();
                                            }}
                                            disabled={publishing}
                                        >
                                            {publishing
                                                ? __('Pubblicazione...', 'rndt-manager')
                                                : __('Pubblica su CSW', 'rndt-manager')
                                            }
                                        </MenuItem>
                                    )}
                                    {geoserverConfigured && (
                                        <MenuItem
                                            icon={upload}
                                            onClick={() => {
                                                onPublishGeoServer();
                                                onClose();
                                            }}
                                            disabled={publishing}
                                        >
                                            {publishing
                                                ? __('Associazione...', 'rndt-manager')
                                                : __('Associa layer GeoServer', 'rndt-manager')
                                            }
                                        </MenuItem>
                                    )}
                                </MenuGroup>
                            )}
                        </>
                    )}
                </DropdownMenu>
            </div>
        </div>
    );
};

export default WizardActions;
