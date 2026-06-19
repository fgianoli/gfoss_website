/**
 * FieldWrapper Component
 *
 * Wrapper comune per tutti i campi con label, help e gestione errori.
 *
 * @package RNDT_Manager
 */

import { __ } from '@wordpress/i18n';

/**
 * FieldWrapper Component
 */
const FieldWrapper = ({
    label,
    required = false,
    error = null,
    warning = null,
    help = null,
    children,
    className = '',
}) => {
    const hasError = error && error.message;
    const hasWarning = warning && warning.message;

    return (
        <div className={`rndt-field ${hasError ? 'has-error' : ''} ${hasWarning ? 'has-warning' : ''} ${className}`}>
            {label && (
                <label className="rndt-field__label">
                    {label}
                    {required && (
                        <span className="rndt-field__required" title={__('Campo obbligatorio', 'rndt-manager')}>
                            *
                        </span>
                    )}
                </label>
            )}

            <div className="rndt-field__control">
                {children}
            </div>

            {help && !hasError && (
                <p className="rndt-field__help">{help}</p>
            )}

            {hasError && (
                <p className="rndt-field__error">
                    <span className="dashicons dashicons-warning" />
                    {error.message}
                </p>
            )}

            {hasWarning && !hasError && (
                <p className="rndt-field__warning">
                    <span className="dashicons dashicons-info" />
                    {warning.message}
                </p>
            )}
        </div>
    );
};

export default FieldWrapper;
