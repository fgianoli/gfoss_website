/**
 * Resource Type Selector Component
 *
 * @package RNDT_Manager
 */

import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Tipi di risorsa disponibili
 */
const resourceTypes = [
    {
        id: 'dataset',
        title: __('Dataset', 'rndt-manager'),
        description: __('Set di dati geografici identificabile.', 'rndt-manager'),
        icon: 'database',
    },
    {
        id: 'series',
        title: __('Serie', 'rndt-manager'),
        description: __('Collezione di dataset con specifiche comuni.', 'rndt-manager'),
        icon: 'portfolio',
    },
    {
        id: 'service',
        title: __('Servizio', 'rndt-manager'),
        description: __('Servizio OGC (WMS, WFS, CSW, ecc.).', 'rndt-manager'),
        icon: 'cloud',
    },
    {
        id: 'application',
        title: __('Applicazione', 'rndt-manager'),
        description: __('Applicazione o software geografico.', 'rndt-manager'),
        icon: 'admin-plugins',
    },
];

/**
 * ResourceTypeSelector Component
 */
const ResourceTypeSelector = ({ onSelect, onImportXml }) => {
    const [selected, setSelected] = useState(null);

    const handleSelect = (type) => {
        setSelected(type);
    };

    const handleConfirm = () => {
        if (selected) {
            onSelect(selected);
        }
    };

    return (
        <div className="rndt-type-selector">
            <div className="rndt-type-selector__header">
                <h2>{__('Seleziona il tipo di risorsa', 'rndt-manager')}</h2>
                <p>{__('Scegli il tipo di metadato che vuoi creare.', 'rndt-manager')}</p>
            </div>

            <div className="rndt-type-selector__grid">
                {resourceTypes.map((type) => (
                    <Card
                        key={type.id}
                        className={`rndt-type-selector__card ${selected === type.id ? 'is-selected' : ''}`}
                        onClick={() => handleSelect(type.id)}
                        isElevated={selected === type.id}
                    >
                        <CardHeader>
                            <Icon icon={type.icon} size={32} />
                            <h3>{type.title}</h3>
                        </CardHeader>
                        <CardBody>
                            <p>{type.description}</p>
                        </CardBody>
                    </Card>
                ))}
            </div>

            <div className="rndt-type-selector__actions">
                <Button
                    variant="primary"
                    onClick={handleConfirm}
                    disabled={!selected}
                >
                    {__('Continua', 'rndt-manager')}
                </Button>
            </div>

            {onImportXml && (
                <div className="rndt-type-selector__import">
                    <p>{__('oppure', 'rndt-manager')}</p>
                    <Button
                        variant="secondary"
                        icon="upload"
                        onClick={onImportXml}
                    >
                        {__('Importa da XML', 'rndt-manager')}
                    </Button>
                </div>
            )}
        </div>
    );
};

export default ResourceTypeSelector;
