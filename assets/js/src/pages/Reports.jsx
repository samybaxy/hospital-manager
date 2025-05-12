import React from 'react';
import Card from '../components/Card';

const Reports = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Reports</h1>
      <Card title="Hospital Reports">
        <p className="text-gray-600">View and generate reports here.</p>
      </Card>
    </div>
  );
};

export default Reports;
