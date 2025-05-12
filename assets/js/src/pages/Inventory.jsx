import React from 'react';
import Card from '../components/Card';

const Inventory = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Inventory</h1>
      <Card title="Medical Inventory">
        <p className="text-gray-600">Manage medical supplies and equipment here.</p>
      </Card>
    </div>
  );
};

export default Inventory;
