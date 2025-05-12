import React from 'react';
import Card from '../components/Card';

const Patients = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Patients</h1>
      <Card title="Patient Management">
        <p className="text-gray-600">Manage your hospital's patients here.</p>
      </Card>
    </div>
  );
};

export default Patients;
