/**
 * Wizard Stepper Component
 *
 * @package RNDT_Manager
 */

import { __ } from '@wordpress/i18n';

/**
 * WizardStepper Component
 */
const WizardStepper = ({ steps, currentStep, onStepClick, getStepErrors, validation }) => {
    return (
        <div className="rndt-stepper">
            <div className="rndt-stepper__track">
                {steps.map((step, index) => {
                    const isActive = index === currentStep;
                    const isCompleted = index < currentStep;
                    const errors = getStepErrors(step.id);
                    const hasErrors = errors.length > 0;

                    // Errori hanno priorità: rosso anche se step attivo
                    let statusClass = '';
                    if (hasErrors) statusClass = 'has-errors';
                    else if (isActive) statusClass = 'is-active';
                    else if (isCompleted) statusClass = 'is-completed';

                    return (
                        <div
                            key={step.id}
                            className={`rndt-stepper__step ${statusClass}`}
                            onClick={() => onStepClick(index)}
                            role="button"
                            tabIndex={0}
                            onKeyPress={(e) => e.key === 'Enter' && onStepClick(index)}
                        >
                            <div className="rndt-stepper__indicator">
                                {hasErrors ? (
                                    <span className="dashicons dashicons-warning" />
                                ) : isCompleted ? (
                                    <span className="dashicons dashicons-yes" />
                                ) : (
                                    <span className="rndt-stepper__number">{index + 1}</span>
                                )}
                            </div>

                            <div className="rndt-stepper__label">
                                <span className="rndt-stepper__title">{step.title}</span>
                                {step.required && (
                                    <span className="rndt-stepper__required" title={__('Obbligatorio', 'rndt-manager')}>*</span>
                                )}
                                {hasErrors && (
                                    <span className="rndt-stepper__error-count">
                                        ({errors.length})
                                    </span>
                                )}
                            </div>

                            {index < steps.length - 1 && (
                                <div className="rndt-stepper__connector" />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default WizardStepper;
