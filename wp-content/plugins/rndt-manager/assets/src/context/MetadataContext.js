/**
 * Context per gestione stato metadati
 *
 * @package RNDT_Manager
 */

import { createContext, useContext, useReducer, useCallback } from '@wordpress/element';

// Azioni
const ACTIONS = {
    SET_FIELD: 'SET_FIELD',
    SET_FIELDS: 'SET_FIELDS',
    SET_METADATA: 'SET_METADATA',
    ADD_REPEATABLE: 'ADD_REPEATABLE',
    UPDATE_REPEATABLE: 'UPDATE_REPEATABLE',
    REMOVE_REPEATABLE: 'REMOVE_REPEATABLE',
    SET_VALIDATION: 'SET_VALIDATION',
    CLEAR_VALIDATION: 'CLEAR_VALIDATION',
    SET_DIRTY: 'SET_DIRTY',
};

// Stato iniziale
const initialState = {
    metadata: {},
    validation: {
        errors: [],
        warnings: [],
        valid: null,
    },
    isDirty: false,
};

/**
 * Reducer per gestione stato
 */
function metadataReducer(state, action) {
    switch (action.type) {
        case ACTIONS.SET_FIELD:
            return {
                ...state,
                metadata: {
                    ...state.metadata,
                    [action.field]: action.value,
                },
                isDirty: true,
            };

        case ACTIONS.SET_FIELDS:
            return {
                ...state,
                metadata: {
                    ...state.metadata,
                    ...action.fields,
                },
                isDirty: true,
            };

        case ACTIONS.SET_METADATA:
            return {
                ...state,
                metadata: action.metadata,
                isDirty: false,
            };

        case ACTIONS.ADD_REPEATABLE:
            const currentItems = state.metadata[action.field] || [];
            return {
                ...state,
                metadata: {
                    ...state.metadata,
                    [action.field]: [...currentItems, action.item],
                },
                isDirty: true,
            };

        case ACTIONS.UPDATE_REPEATABLE:
            const items = [...(state.metadata[action.field] || [])];
            items[action.index] = action.item;
            return {
                ...state,
                metadata: {
                    ...state.metadata,
                    [action.field]: items,
                },
                isDirty: true,
            };

        case ACTIONS.REMOVE_REPEATABLE:
            return {
                ...state,
                metadata: {
                    ...state.metadata,
                    [action.field]: (state.metadata[action.field] || []).filter(
                        (_, i) => i !== action.index
                    ),
                },
                isDirty: true,
            };

        case ACTIONS.SET_VALIDATION:
            return {
                ...state,
                validation: {
                    errors: action.errors || [],
                    warnings: action.warnings || [],
                    valid: action.valid,
                },
            };

        case ACTIONS.CLEAR_VALIDATION:
            return {
                ...state,
                validation: initialState.validation,
            };

        case ACTIONS.SET_DIRTY:
            return {
                ...state,
                isDirty: action.isDirty,
            };

        default:
            return state;
    }
}

// Context
const MetadataContext = createContext(null);

/**
 * Provider per MetadataContext
 */
export function MetadataProvider({ children, initialMetadata, resourceType, codelists }) {
    const [state, dispatch] = useReducer(metadataReducer, {
        ...initialState,
        metadata: initialMetadata || {},
    });

    // Azioni
    const setField = useCallback((field, value) => {
        dispatch({ type: ACTIONS.SET_FIELD, field, value });
    }, []);

    const setFields = useCallback((fields) => {
        dispatch({ type: ACTIONS.SET_FIELDS, fields });
    }, []);

    const setMetadata = useCallback((metadata) => {
        dispatch({ type: ACTIONS.SET_METADATA, metadata });
    }, []);

    const addRepeatable = useCallback((field, item) => {
        dispatch({ type: ACTIONS.ADD_REPEATABLE, field, item });
    }, []);

    const updateRepeatable = useCallback((field, index, item) => {
        dispatch({ type: ACTIONS.UPDATE_REPEATABLE, field, index, item });
    }, []);

    const removeRepeatable = useCallback((field, index) => {
        dispatch({ type: ACTIONS.REMOVE_REPEATABLE, field, index });
    }, []);

    const setValidation = useCallback((result) => {
        dispatch({
            type: ACTIONS.SET_VALIDATION,
            errors: result.errors,
            warnings: result.warnings,
            valid: result.valid,
        });
    }, []);

    const clearValidation = useCallback(() => {
        dispatch({ type: ACTIONS.CLEAR_VALIDATION });
    }, []);

    const setDirty = useCallback((isDirty) => {
        dispatch({ type: ACTIONS.SET_DIRTY, isDirty });
    }, []);

    // Getter per campo con validazione errori
    const getFieldError = useCallback((field) => {
        return state.validation.errors.find(e => e.field === field);
    }, [state.validation.errors]);

    const getFieldWarning = useCallback((field) => {
        return state.validation.warnings.find(w => w.field === field);
    }, [state.validation.warnings]);

    const value = {
        // Stato
        metadata: state.metadata,
        validation: state.validation,
        isDirty: state.isDirty,
        resourceType,
        codelists,

        // Azioni
        setField,
        setFields,
        setMetadata,
        addRepeatable,
        updateRepeatable,
        removeRepeatable,
        setValidation,
        clearValidation,
        setDirty,

        // Helpers
        getFieldError,
        getFieldWarning,
    };

    return (
        <MetadataContext.Provider value={value}>
            {children}
        </MetadataContext.Provider>
    );
}

/**
 * Hook per usare il context
 */
export function useMetadata() {
    const context = useContext(MetadataContext);
    if (!context) {
        throw new Error('useMetadata deve essere usato dentro MetadataProvider');
    }
    return context;
}

export default MetadataContext;
