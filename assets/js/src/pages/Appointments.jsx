import React from 'react';
import Card from '../components/Card';

const Appointments = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Appointments</h1>
      <Card title="Appointment Management">
        <p className="text-gray-600">Schedule and manage appointments here.</p>
      </Card>
    </div>
  );
};

export default Appointments;
