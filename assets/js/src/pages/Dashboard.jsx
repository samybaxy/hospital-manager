import React, { useState } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { Link } from 'react-router-dom';

const Dashboard = () => {
  const [stats, setStats] = useState({
    patients: 42,
    doctors: 8,
    appointments: 12,
    departments: 3
  });

  return (
    <div className="space-y-6 px-4 md:px-6 lg:px-8">
      {/* Welcome section and title removed as requested */}
      
      <div className="space-y-8 mt-6">
        <Card title="Dashboard Overview">
          <div className="bg-primary-50 p-4 rounded-md border border-primary-200">
            <h2 className="font-semibold text-primary-900">Quick Stats</h2>
            <div className="grid grid-cols-2 gap-4 mt-3 sm:grid-cols-4">
              <div className="bg-white p-3 rounded shadow-sm text-center">
                <span className="block text-2xl font-bold">{stats.patients}</span>
                <span className="text-sm text-gray-500">Patients</span>
              </div>
              <div className="bg-white p-3 rounded shadow-sm text-center">
                <span className="block text-2xl font-bold">{stats.doctors}</span>
                <span className="text-sm text-gray-500">Doctors</span>
              </div>
              <div className="bg-white p-3 rounded shadow-sm text-center">
                <span className="block text-2xl font-bold">{stats.appointments}</span>
                <span className="text-sm text-gray-500">Appointments</span>
              </div>
              <div className="bg-white p-3 rounded shadow-sm text-center">
                <span className="block text-2xl font-bold">{stats.departments}</span>
                <span className="text-sm text-gray-500">Departments</span>
              </div>
            </div>
          </div>
          
          <div className="mt-6 flex space-x-4">
            <Link to="/patients">
              <Button variant="primary">Add New Patient</Button>
            </Link>
            <Link to="/patients">
              <Button variant="secondary">View All Patients</Button>
            </Link>
          </div>
        </Card>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Recent Appointments">
            <div className="space-y-4">
              <div className="flex justify-between p-2 bg-gray-50 rounded">
                <div>
                  <p className="font-medium">John Doe</p>
                  <p className="text-sm text-gray-600">General Checkup</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium text-primary-700">Today, 2:00 PM</p>
                  <p className="text-xs text-gray-500">Dr. Smith</p>
                </div>
              </div>
              
              <div className="flex justify-between p-2 bg-gray-50 rounded">
                <div>
                  <p className="font-medium">Jane Smith</p>
                  <p className="text-sm text-gray-600">Follow-up</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium text-primary-700">Tomorrow, 10:00 AM</p>
                  <p className="text-xs text-gray-500">Dr. Johnson</p>
                </div>
              </div>
            </div>
          </Card>
          
          <Card title="Hospital Resources">
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span>Available Beds</span>
                <span className="font-medium">12/20</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-primary-600 h-2.5 rounded-full" style={{ width: '60%' }}></div>
              </div>
              
              <div className="flex justify-between items-center mt-4">
                <span>ICU Capacity</span>
                <span className="font-medium">4/8</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-primary-600 h-2.5 rounded-full" style={{ width: '50%' }}></div>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
