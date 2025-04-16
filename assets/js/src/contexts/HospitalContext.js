import React, { createContext, useContext, useReducer } from 'react';

const initialState = {
    patients: [],
    doctors: [],
    appointments: [],
    loading: false,
    error: null
};

export const HospitalContext = createContext(initialState);

const hospitalReducer = (state, action) => {
    switch (action.type) {
        case 'SET_LOADING':
            return { ...state, loading: action.payload };
        case 'SET_ERROR':
            return { ...state, error: action.payload };
        case 'SET_PATIENTS':
            return { ...state, patients: action.payload };
        case 'SET_DOCTORS':
            return { ...state, doctors: action.payload };
        case 'SET_APPOINTMENTS':
            return { ...state, appointments: action.payload };
        default:
            return state;
    }
};

export const HospitalProvider = ({ children }) => {
    const [state, dispatch] = useReducer(hospitalReducer, initialState);

    return (
        <HospitalContext.Provider value={{ state, dispatch }}>
            {children}
        </HospitalContext.Provider>
    );
};

export const useHospital = () => {
    const context = useContext(HospitalContext);
    if (!context) {
        throw new Error('useHospital must be used within a HospitalProvider');
    }
    return context;
};
