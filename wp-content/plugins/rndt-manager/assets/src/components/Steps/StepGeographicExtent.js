/**
 * Step Estensione Geografica
 *
 * @package RNDT_Manager
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, Button, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepGeographicExtent Component
 */
const StepGeographicExtent = ({ codelists }) => {
    const { metadata, setField, setFields, getFieldError } = useMetadata();
    const mapRef = useRef(null);
    const mapInstanceRef = useRef(null);
    const rectangleRef = useRef(null);

    // Preset aree geografiche italiane
    const regionPresets = [
        { value: '', label: __('-- Seleziona preset --', 'rndt-manager') },

        // Macro aree
        { value: 'italia', label: '🇮🇹 Italia', bbox: { west: 6.6, east: 18.5, south: 36.6, north: 47.1 } },
        { value: 'nord-est', label: '📍 Nord Est (Triveneto)', bbox: { west: 10.38, east: 13.92, south: 44.79, north: 47.09 } },
        { value: 'nord-ovest', label: '📍 Nord Ovest', bbox: { west: 6.6, east: 11.5, south: 43.7, north: 46.6 } },
        { value: 'centro', label: '📍 Centro Italia', bbox: { west: 9.7, east: 14.8, south: 41.2, north: 44.5 } },
        { value: 'sud', label: '📍 Sud Italia', bbox: { west: 13.5, east: 18.5, south: 37.9, north: 42.2 } },

        // Regioni - Nord
        { value: 'piemonte', label: 'Piemonte', bbox: { west: 6.6, east: 9.2, south: 44.0, north: 46.5 } },
        { value: 'valle-aosta', label: 'Valle d\'Aosta', bbox: { west: 6.8, east: 7.9, south: 45.5, north: 46.0 } },
        { value: 'lombardia', label: 'Lombardia', bbox: { west: 8.5, east: 11.5, south: 44.7, north: 46.6 } },
        { value: 'trentino-aa', label: 'Trentino-Alto Adige', bbox: { west: 10.38, east: 12.48, south: 45.67, north: 47.09 } },
        { value: 'veneto', label: 'Veneto', bbox: { west: 10.62, east: 13.10, south: 44.79, north: 46.68 } },
        { value: 'friuli-vg', label: 'Friuli Venezia Giulia', bbox: { west: 12.3, east: 13.92, south: 45.58, north: 46.65 } },
        { value: 'liguria', label: 'Liguria', bbox: { west: 7.5, east: 10.1, south: 43.8, north: 44.7 } },
        { value: 'emilia-romagna', label: 'Emilia-Romagna', bbox: { west: 9.2, east: 12.8, south: 43.7, north: 45.2 } },

        // Regioni - Centro
        { value: 'toscana', label: 'Toscana', bbox: { west: 9.7, east: 12.4, south: 42.2, north: 44.5 } },
        { value: 'umbria', label: 'Umbria', bbox: { west: 12.0, east: 13.3, south: 42.4, north: 43.4 } },
        { value: 'marche', label: 'Marche', bbox: { west: 11.7, east: 13.9, south: 42.7, north: 44.0 } },
        { value: 'lazio', label: 'Lazio', bbox: { west: 11.4, east: 14.0, south: 41.2, north: 42.9 } },
        { value: 'abruzzo', label: 'Abruzzo', bbox: { west: 13.0, east: 14.8, south: 41.7, north: 42.9 } },
        { value: 'molise', label: 'Molise', bbox: { west: 13.9, east: 15.2, south: 41.4, north: 42.1 } },

        // Regioni - Sud
        { value: 'campania', label: 'Campania', bbox: { west: 13.5, east: 15.8, south: 39.9, north: 41.5 } },
        { value: 'puglia', label: 'Puglia', bbox: { west: 14.9, east: 18.5, south: 39.8, north: 42.2 } },
        { value: 'basilicata', label: 'Basilicata', bbox: { west: 15.3, east: 16.9, south: 39.9, north: 41.1 } },
        { value: 'calabria', label: 'Calabria', bbox: { west: 15.6, east: 17.2, south: 37.9, north: 40.1 } },
        { value: 'sicilia', label: 'Sicilia', bbox: { west: 12.4, east: 15.7, south: 36.6, north: 38.8 } },
        { value: 'sardegna', label: 'Sardegna', bbox: { west: 8.1, east: 9.8, south: 38.9, north: 41.3 } },

        // Province Veneto
        { value: 'prov-vicenza', label: '🏛️ Provincia di Vicenza', bbox: { west: 11.15, east: 11.95, south: 45.45, north: 46.05 } },
        { value: 'prov-verona', label: '🏛️ Provincia di Verona', bbox: { west: 10.62, east: 11.53, south: 45.13, north: 45.87 } },
        { value: 'prov-padova', label: '🏛️ Provincia di Padova', bbox: { west: 11.35, east: 12.25, south: 45.08, north: 45.65 } },
        { value: 'prov-treviso', label: '🏛️ Provincia di Treviso', bbox: { west: 11.65, east: 12.60, south: 45.55, north: 46.10 } },
        { value: 'prov-venezia', label: '🏛️ Provincia di Venezia', bbox: { west: 12.10, east: 13.10, south: 45.20, north: 45.80 } },
        { value: 'prov-belluno', label: '🏛️ Provincia di Belluno', bbox: { west: 11.55, east: 12.65, south: 45.87, north: 46.68 } },
        { value: 'prov-rovigo', label: '🏛️ Provincia di Rovigo', bbox: { west: 11.20, east: 12.40, south: 44.79, north: 45.15 } },

        // Comuni principali Veneto
        { value: 'comune-vicenza', label: '🏠 Comune di Vicenza', bbox: { west: 11.49, east: 11.62, south: 45.50, north: 45.59 } },
        { value: 'comune-verona', label: '🏠 Comune di Verona', bbox: { west: 10.92, east: 11.08, south: 45.40, north: 45.50 } },
        { value: 'comune-padova', label: '🏠 Comune di Padova', bbox: { west: 11.82, east: 11.95, south: 45.37, north: 45.45 } },
        { value: 'comune-venezia', label: '🏠 Comune di Venezia', bbox: { west: 12.20, east: 12.42, south: 45.40, north: 45.50 } },
        { value: 'comune-treviso', label: '🏠 Comune di Treviso', bbox: { west: 12.20, east: 12.30, south: 45.64, north: 45.71 } },
    ];

    // Inizializza mappa Leaflet
    useEffect(() => {
        if (typeof window !== 'undefined' && window.L && mapRef.current && !mapInstanceRef.current) {
            const L = window.L;

            // Crea mappa centrata sull'Italia
            const map = L.map(mapRef.current).setView([42.5, 12.5], 5);

            // Aggiungi layer OSM
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            mapInstanceRef.current = map;

            // Abilita disegno rettangolo
            map.on('click', (e) => {
                // Gestito dal draw control
            });

            // Se ci sono coordinate esistenti, mostra il rettangolo
            if (metadata.bbox_west && metadata.bbox_east && metadata.bbox_south && metadata.bbox_north) {
                updateMapRectangle();
            }
        }

        return () => {
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
        };
    }, []);

    // Aggiorna rettangolo sulla mappa quando cambiano le coordinate
    useEffect(() => {
        updateMapRectangle();
    }, [metadata.bbox_west, metadata.bbox_east, metadata.bbox_south, metadata.bbox_north]);

    const updateMapRectangle = () => {
        if (!mapInstanceRef.current || !window.L) return;

        const L = window.L;
        const { bbox_west, bbox_east, bbox_south, bbox_north } = metadata;

        if (bbox_west && bbox_east && bbox_south && bbox_north) {
            const bounds = [
                [parseFloat(bbox_south), parseFloat(bbox_west)],
                [parseFloat(bbox_north), parseFloat(bbox_east)]
            ];

            if (rectangleRef.current) {
                rectangleRef.current.setBounds(bounds);
            } else {
                rectangleRef.current = L.rectangle(bounds, {
                    color: '#0073aa',
                    weight: 2,
                    fillOpacity: 0.2
                }).addTo(mapInstanceRef.current);
            }

            mapInstanceRef.current.fitBounds(bounds, { padding: [20, 20] });
        } else if (rectangleRef.current) {
            mapInstanceRef.current.removeLayer(rectangleRef.current);
            rectangleRef.current = null;
        }
    };

    // Applica preset
    const handlePresetChange = (preset) => {
        const selected = regionPresets.find(r => r.value === preset);
        if (selected && selected.bbox) {
            setFields({
                bbox_west: selected.bbox.west.toString(),
                bbox_east: selected.bbox.east.toString(),
                bbox_south: selected.bbox.south.toString(),
                bbox_north: selected.bbox.north.toString(),
                geographic_description: selected.label,
            });
        }
    };

    return (
        <div className="rndt-step rndt-step--geographic">
            <h3>{__('Estensione geografica', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Definisci l\'area geografica coperta dalla risorsa.', 'rndt-manager')}
            </p>

            {/* Preset regioni */}
            <FieldWrapper
                label={__('Preset area', 'rndt-manager')}
                help={__('Seleziona un\'area predefinita.', 'rndt-manager')}
            >
                <SelectControl
                    value=""
                    options={regionPresets}
                    onChange={handlePresetChange}
                />
            </FieldWrapper>

            {/* Mappa */}
            <FieldWrapper
                label={__('Mappa', 'rndt-manager')}
            >
                <div
                    ref={mapRef}
                    className="rndt-map"
                    style={{ height: '400px', width: '100%' }}
                />
            </FieldWrapper>

            {/* Bounding Box manuale */}
            <div className="rndt-bbox-inputs">
                <FieldWrapper
                    label={__('Longitudine Ovest', 'rndt-manager')}
                    required={true}
                    error={getFieldError('bbox_west') || getFieldError('bbox')}
                >
                    <TextControl
                        type="number"
                        step="0.0001"
                        min="-180"
                        max="180"
                        value={metadata.bbox_west || ''}
                        onChange={(value) => setField('bbox_west', value)}
                        placeholder="-180 a 180"
                    />
                </FieldWrapper>

                <FieldWrapper
                    label={__('Longitudine Est', 'rndt-manager')}
                    required={true}
                    error={getFieldError('bbox_east')}
                >
                    <TextControl
                        type="number"
                        step="0.0001"
                        min="-180"
                        max="180"
                        value={metadata.bbox_east || ''}
                        onChange={(value) => setField('bbox_east', value)}
                        placeholder="-180 a 180"
                    />
                </FieldWrapper>

                <FieldWrapper
                    label={__('Latitudine Sud', 'rndt-manager')}
                    required={true}
                    error={getFieldError('bbox_south')}
                >
                    <TextControl
                        type="number"
                        step="0.0001"
                        min="-90"
                        max="90"
                        value={metadata.bbox_south || ''}
                        onChange={(value) => setField('bbox_south', value)}
                        placeholder="-90 a 90"
                    />
                </FieldWrapper>

                <FieldWrapper
                    label={__('Latitudine Nord', 'rndt-manager')}
                    required={true}
                    error={getFieldError('bbox_north')}
                >
                    <TextControl
                        type="number"
                        step="0.0001"
                        min="-90"
                        max="90"
                        value={metadata.bbox_north || ''}
                        onChange={(value) => setField('bbox_north', value)}
                        placeholder="-90 a 90"
                    />
                </FieldWrapper>
            </div>

            {/* Descrizione geografica */}
            <FieldWrapper
                label={__('Descrizione geografica', 'rndt-manager')}
                help={__('Nome dell\'area geografica (es. Lombardia, Italia).', 'rndt-manager')}
            >
                <TextControl
                    value={metadata.geographic_description || ''}
                    onChange={(value) => setField('geographic_description', value)}
                />
            </FieldWrapper>
        </div>
    );
};

export default StepGeographicExtent;
