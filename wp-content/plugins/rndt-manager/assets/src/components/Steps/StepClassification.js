/**
 * Step Classificazione
 *
 * @package RNDT_Manager
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl, Button, SelectControl } from '@wordpress/components';
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

/**
 * StepClassification Component
 */
const StepClassification = ({ codelists, resourceType }) => {
    const { metadata, setField, addRepeatable, removeRepeatable, getFieldError } = useMetadata();
    const [newKeyword, setNewKeyword] = useState('');
    const [newKeywordThesaurus, setNewKeywordThesaurus] = useState('free');

    // Topic categories - il PHP invia un oggetto {code: {it, en}}, convertiamo in array [{value, label}]
    const rawTopicCategories = codelists?.topicCategories || {};
    const topicCategories = Array.isArray(rawTopicCategories)
        ? rawTopicCategories
        : Object.entries(rawTopicCategories).map(([code, data]) => ({
            value: code,
            label: (typeof data === 'object' ? (data.it || data.en || code) : data),
        }));

    // INSPIRE themes - il PHP invia un oggetto {code: {it, en, annex, uri}}, convertiamo in array [{code, label_it, label}]
    const rawInspireThemes = codelists?.inspireThemes || {};
    const inspireThemes = Array.isArray(rawInspireThemes)
        ? rawInspireThemes
        : Object.entries(rawInspireThemes).map(([code, data]) => ({
            code: code,
            label_it: (typeof data === 'object' ? (data.it || data.en || code) : data),
            label: (typeof data === 'object' ? (data.en || data.it || code) : data),
            annex: (typeof data === 'object' ? data.annex : ''),
        }));

    // Gestione topic categories (array di codici)
    const selectedCategories = metadata.topic_categories || [];

    const handleCategoryChange = (category, checked) => {
        const newCategories = checked
            ? [...selectedCategories, category]
            : selectedCategories.filter(c => c !== category);
        setField('topic_categories', newCategories);
    };

    // Gestione INSPIRE themes (array di codici)
    const selectedThemes = metadata.inspire_themes || [];

    const handleThemeChange = (theme, checked) => {
        const newThemes = checked
            ? [...selectedThemes, theme]
            : selectedThemes.filter(t => t !== theme);
        setField('inspire_themes', newThemes);
    };

    // Gestione keywords
    const keywords = metadata.keywords || [];

    const handleAddKeyword = () => {
        if (newKeyword.trim()) {
            addRepeatable('keywords', {
                keyword: newKeyword.trim(),
                thesaurus_name: newKeywordThesaurus,
                thesaurus_title: newKeywordThesaurus === 'inspire'
                    ? 'GEMET - INSPIRE themes'
                    : newKeywordThesaurus === 'gemet'
                    ? 'GEMET - Concepts'
                    : '',
            });
            setNewKeyword('');
        }
    };

    return (
        <div className="rndt-step rndt-step--classification">
            <h3>{__('Classificazione', 'rndt-manager')}</h3>
            <p className="rndt-step__description">
                {__('Classificazione tematica e parole chiave.', 'rndt-manager')}
            </p>

            {/* Topic Categories (solo per dataset/series) */}
            {resourceType !== 'service' && (
                <FieldWrapper
                    label={__('Categorie tematiche', 'rndt-manager')}
                    required={true}
                    error={getFieldError('topic_categories')}
                    help={__('Seleziona almeno una categoria ISO 19115.', 'rndt-manager')}
                >
                    <div className="rndt-checkbox-grid">
                        {topicCategories.map((cat) => (
                            <CheckboxControl
                                key={cat.value}
                                label={cat.label}
                                checked={selectedCategories.includes(cat.value)}
                                onChange={(checked) => handleCategoryChange(cat.value, checked)}
                            />
                        ))}
                    </div>
                </FieldWrapper>
            )}

            {/* INSPIRE Themes */}
            <FieldWrapper
                label={__('Temi INSPIRE', 'rndt-manager')}
                required={true}
                error={getFieldError('keywords')}
                help={__('Seleziona almeno un tema INSPIRE.', 'rndt-manager')}
            >
                <div className="rndt-checkbox-grid">
                    {inspireThemes.length > 0 ? (
                        inspireThemes.map((theme) => (
                            <CheckboxControl
                                key={theme.code}
                                label={theme.label_it || theme.label}
                                checked={selectedThemes.includes(theme.code)}
                                onChange={(checked) => handleThemeChange(theme.code, checked)}
                            />
                        ))
                    ) : (
                        <p>{__('Temi INSPIRE non disponibili.', 'rndt-manager')}</p>
                    )}
                </div>
            </FieldWrapper>

            {/* Keywords libere */}
            <FieldWrapper
                label={__('Parole chiave aggiuntive', 'rndt-manager')}
                help={__('Parole chiave libere o da altri thesaurus.', 'rndt-manager')}
            >
                <div className="rndt-keywords">
                    {/* Lista keywords esistenti */}
                    <div className="rndt-keywords__list">
                        {keywords.map((kw, index) => (
                            <div key={index} className="rndt-keywords__item">
                                <span className="rndt-keywords__text">{kw.keyword}</span>
                                <span className="rndt-keywords__thesaurus">
                                    ({kw.thesaurus_name || 'free'})
                                </span>
                                <Button
                                    icon="no-alt"
                                    isSmall
                                    isDestructive
                                    onClick={() => removeRepeatable('keywords', index)}
                                    label={__('Rimuovi', 'rndt-manager')}
                                />
                            </div>
                        ))}
                    </div>

                    {/* Aggiungi nuova keyword */}
                    <div className="rndt-keywords__add">
                        <TextControl
                            value={newKeyword}
                            onChange={setNewKeyword}
                            placeholder={__('Nuova parola chiave...', 'rndt-manager')}
                        />
                        <SelectControl
                            value={newKeywordThesaurus}
                            options={[
                                { value: 'free', label: __('Libera', 'rndt-manager') },
                                { value: 'inspire', label: 'INSPIRE' },
                                { value: 'gemet', label: 'GEMET' },
                            ]}
                            onChange={setNewKeywordThesaurus}
                        />
                        <Button
                            variant="secondary"
                            onClick={handleAddKeyword}
                            disabled={!newKeyword.trim()}
                        >
                            {__('Aggiungi', 'rndt-manager')}
                        </Button>
                    </div>
                </div>
            </FieldWrapper>
        </div>
    );
};

export default StepClassification;
