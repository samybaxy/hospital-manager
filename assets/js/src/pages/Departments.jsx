import React from 'react';
import Card from '../components/Card';

const Departments = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Departments</h1>
      <Card title="Department Management">
        <p className="text-gray-600">Manage hospital departments here.</p>
      </Card>
    </div>
  );
};

export default Departments;
