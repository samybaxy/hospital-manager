import React from 'react';

const LoadingSpinner = ({ size = 'md', className = '' }) => {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
    xl: 'h-16 w-16'
  };

  return (
    <div className={`animate-spin rounded-full border-t-2 border-b-2 border-primary-600 ${sizeClasses[size]} ${className}`}></div>
  );
};

const LoadingState = ({ message = 'Loading...', size = 'md' }) => {
  return (
    <div className="flex flex-col items-center justify-center py-16 space-y-4">
      <LoadingSpinner size={size} />
      <p className="text-gray-600 text-center">{message}</p>
    </div>
  );
};

export { LoadingSpinner, LoadingState };
export default LoadingState;
