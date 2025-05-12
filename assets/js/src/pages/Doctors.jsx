import React from 'react';
import Card from '../components/Card';

const Doctors = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Doctors</h1>
      <Card title="Doctor Management">
        <p className="text-gray-600">Manage your hospital's doctors and staff here.</p>
      </Card>
    </div>
  );
};

export default Doctors;
