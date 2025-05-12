import React, { useState, useEffect } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';

const Statistics = () => {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const { apiUrl, nonce } = window.hospitalManagerData || {};
        
        if (!apiUrl) {
          throw new Error('API URL not available');
        }
        
        const response = await fetch(`${apiUrl}/stats`, {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        });
        
        if (!response.ok) {
          throw new Error('Failed to fetch statistics');
        }
        
        const data = await response.json();
        setStats(data);
        setLoading(false);
      } catch (err) {
        console.error('Error fetching statistics:', err);
        setError(err.message);
        setLoading(false);
      }
    };
    
    fetchStats();
  }, []);
  
  if (loading) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4">
        <p>Error: {error}</p>
        <Button 
          variant="primary" 
          className="mt-4"
          onClick={() => window.location.reload()}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Hospital Statistics</h1>
      <p className="text-lg text-gray-600">Overview of key hospital metrics</p>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="bg-blue-50">
          <div className="p-4">
            <h3 className="text-lg font-medium text-blue-800">Total Patients</h3>
            <div className="mt-2 text-3xl font-bold text-blue-900">
              {stats?.totalPatients || 0}
            </div>
          </div>
        </Card>
        
        <Card className="bg-green-50">
          <div className="p-4">
            <h3 className="text-lg font-medium text-green-800">Active Doctors</h3>
            <div className="mt-2 text-3xl font-bold text-green-900">
              {stats?.activeDoctors || 0}
            </div>
          </div>
        </Card>
        
        <Card className="bg-purple-50">
          <div className="p-4">
            <h3 className="text-lg font-medium text-purple-800">Today's Appointments</h3>
            <div className="mt-2 text-3xl font-bold text-purple-900">
              {stats?.todayAppointments || 0}
            </div>
          </div>
        </Card>
        
        <Card className="bg-amber-50">
          <div className="p-4">
            <h3 className="text-lg font-medium text-amber-800">Monthly Revenue</h3>
            <div className="mt-2 text-3xl font-bold text-amber-900">
              ${stats?.monthlyRevenue?.toLocaleString() || 0}
            </div>
          </div>
        </Card>
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <div className="p-4">
            <h3 className="text-lg font-medium text-gray-900">Patient Statistics</h3>
            <div className="mt-4 space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-gray-600">New patients (this month)</span>
                <span className="font-semibold">{stats?.newPatientsThisMonth || 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Average patient age</span>
                <span className="font-semibold">{stats?.averagePatientAge || 0} years</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Male/Female ratio</span>
                <span className="font-semibold">{stats?.maleToFemaleRatio || '0:0'}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Average stay duration</span>
                <span className="font-semibold">{stats?.averageStayDuration || 0} days</span>
              </div>
            </div>
          </div>
        </Card>
        
        <Card>
          <div className="p-4">
            <h3 className="text-lg font-medium text-gray-900">Department Statistics</h3>
            <div className="mt-4 space-y-4">
              {stats?.departmentStats ? (
                Object.entries(stats.departmentStats).map(([department, count]) => (
                  <div key={department} className="flex justify-between items-center">
                    <span className="text-gray-600">{department}</span>
                    <span className="font-semibold">{count} patients</span>
                  </div>
                ))
              ) : (
                <p className="text-gray-500">No department data available</p>
              )}
            </div>
          </div>
        </Card>
      </div>
      
      <Card>
        <div className="p-4">
          <h3 className="text-lg font-medium text-gray-900">Lab Investigation Summary</h3>
          <div className="mt-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="bg-gray-50 p-4 rounded-md">
                <h4 className="text-sm font-medium text-gray-500">Total Tests</h4>
                <p className="mt-2 text-2xl font-semibold">{stats?.labStats?.totalTests || 0}</p>
              </div>
              <div className="bg-gray-50 p-4 rounded-md">
                <h4 className="text-sm font-medium text-gray-500">Pending Results</h4>
                <p className="mt-2 text-2xl font-semibold">{stats?.labStats?.pendingResults || 0}</p>
              </div>
              <div className="bg-gray-50 p-4 rounded-md">
                <h4 className="text-sm font-medium text-gray-500">Completed Today</h4>
                <p className="mt-2 text-2xl font-semibold">{stats?.labStats?.completedToday || 0}</p>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
};

export default Statistics;
