import React from 'react';

const Header = ({ title }) => {
  return (
    <div className="shadow" style={{ backgroundColor: 'rgb(247, 251, 255)' }}>
      <div className="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
        <h1 className="text-3xl font-bold text-gray-900">{title || 'Hospital Manager'}</h1>
      </div>
    </div>
  );
};

export default Header;
