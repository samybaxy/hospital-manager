import React from 'react';
import Card from '../components/Card';

const Settings = () => {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Settings</h1>
      <Card title="Hospital Settings">
        <p className="text-gray-600">Configure system settings here.</p>
      </Card>
    </div>
  );
};

export default Settings;
