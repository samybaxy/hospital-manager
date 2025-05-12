import React from 'react';
import Card from '../components/Card';

const Billing = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Billing</h1>
      <Card title="Patient Billing">
        <p className="text-gray-600">Manage billing and invoices here.</p>
      </Card>
    </div>
  );
};

export default Billing;
