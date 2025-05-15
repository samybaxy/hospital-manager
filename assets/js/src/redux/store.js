/**
 * Redux store configuration for the hospital manager application
 */

import { configureStore } from '@reduxjs/toolkit';
import accessReducer from './accessSlice';

// Configure the Redux store
export const store = configureStore({
  reducer: {
    access: accessReducer,
    // Add other reducers here as needed
  },
  // Add middleware or other configuration as needed
});

export default store;
