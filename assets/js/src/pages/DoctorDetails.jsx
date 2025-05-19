import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';

// CSS utility for line clamping
const lineClampStyle = {
  display: '-webkit-box',
  WebkitLineClamp: '2',
  WebkitBoxOrient: 'vertical',
  overflow: 'hidden'
};

const DoctorDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [doctor, setDoctor] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('profile');
  const [patients, setPatients] = useState([]);
  const [patientsLoading, setPatientsLoading] = useState(false);
  const [patientStats, setPatientStats] = useState({
    total: 0,
    active: 0,
    recentVisits: 0
  });
  const [scheduleStats, setScheduleStats] = useState({
    totalAppointments: 0,
    upcomingAppointments: 0,
    completedAppointments: 0
  });

  // Function to fetch doctor's patients when patients tab is clicked
  const fetchPatients = useCallback(async () => {
    if (!id) return;
    
    try {
      setPatientsLoading(true);
      const response = await api.get(`/doctors/${id}/patients`);
      
      if (response.data && response.data.data) {
        setPatients(response.data.data);
      } else if (response.data) {
        setPatients(response.data);
      } else {
        setPatients([]);
      }

      // Set mock patient statistics for UI demonstration
      setPatientStats({
        total: response.data?.meta?.total || Math.floor(Math.random() * 50) + 10,
        active: Math.floor(Math.random() * 20) + 5,
        recentVisits: Math.floor(Math.random() * 10) + 1
      });
    } catch (err) {
      console.error('Error fetching patients:', err);
      setError('Failed to load patients list');
    } finally {
      setPatientsLoading(false);
    }
  }, [id]);

  // Effect to fetch patients when tab changes to patients
  useEffect(() => {
    if (activeTab === 'patients' && patients.length === 0) {
      fetchPatients();
    }
  }, [activeTab, fetchPatients, patients.length]);

  useEffect(() => {
    const fetchDoctor = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/doctors/${id}`);

        // Check for the structure of the response and extract the doctor data properly
        if (response.data && response.data.data) {
          // If the API returns nested data structure
          setDoctor(response.data.data);
        } else if (response.data) {
          // If the API returns flat data structure
          setDoctor(response.data);
        }

        // Set mock schedule statistics for UI demonstration
        setScheduleStats({
          totalAppointments: Math.floor(Math.random() * 100) + 20,
          upcomingAppointments: Math.floor(Math.random() * 15) + 2,
          completedAppointments: Math.floor(Math.random() * 80) + 10
        });
      } catch (err) {
        console.error('Error fetching doctor details:', err);
        setError('Failed to load doctor details. The doctor may not exist or you may not have permission to view it.');
      } finally {
        setLoading(false);
      }
    };
    
    fetchDoctor();
  }, [id]);

  const handleDelete = async () => {
    if (!window.confirm('Are you sure you want to delete this doctor? This action cannot be undone.')) {
      return;
    }

    try {
      setLoading(true);
      await api.delete(`/doctors/${id}`);
      navigate('/doctors', { replace: true });
    } catch (err) {
      console.error('Error deleting doctor:', err);
      setError('Failed to delete doctor. Please try again.');
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center p-12">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mt-8">
        <Card>
          <div className="bg-red-50 p-4 rounded-md border border-red-200 text-red-700">
            {error}
          </div>
          <div className="mt-4">
            <Link to="/doctors">
              <Button variant="secondary">Return to Doctors</Button>
            </Link>
          </div>
        </Card>
      </div>
    );
  }

  if (!doctor) {
    return (
      <div className="mt-8">
        <Card>
          <div className="text-center p-8">
            <p className="text-gray-600">Doctor not found</p>
          </div>
          <div className="mt-4">
            <Link to="/doctors">
              <Button variant="secondary">Return to Doctors</Button>
            </Link>
          </div>
        </Card>
      </div>
    );
  }
  
  // Access data with fallbacks for different API response formats
  const firstName = doctor.first_name || (doctor.name ? doctor.name.split(' ')[0] : '');
  const lastName = doctor.last_name || (doctor.name ? doctor.name.split(' ').slice(1).join(' ') : '');
  const fullName = doctor.fullName || `${firstName} ${lastName}`.trim();
  const specialty = doctor.specialty || 'General Practice';
  const licenseNumber = doctor.license_number || doctor.licenseNumber || 'N/A';

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Doctor Details</h1>
          <p className="text-gray-600">
            Doctor ID: {doctor.id || '-'}
          </p>
        </div>
        <div className="flex gap-2 mt-2 md:mt-0">
          <Link to={`/doctors/${id}/edit`}>
            <Button variant="primary">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
              </svg>
              Edit Doctor
            </Button>
          </Link>
          <Button variant="danger" onClick={handleDelete}>
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
            Delete
          </Button>
        </div>
      </div>

      {/* Doctor Header Card with Photo */}
      <Card>
        <div className="flex flex-col md:flex-row items-center md:items-start p-4">
          <div className="h-32 w-32 rounded-full bg-gray-200 flex items-center justify-center mb-4 md:mb-0 md:mr-6 text-4xl font-bold text-gray-500">
            {firstName[0]}{lastName[0]}
          </div>
          <div className="flex-1">
            <h2 className="text-xl font-semibold mb-2">{fullName}</h2>
            <div className="mb-3">
              <span className="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {specialty}
              </span>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <div className="text-blue-600 text-sm font-medium mb-1">License Number</div>
                <div className="text-gray-900 font-semibold">{licenseNumber}</div>
              </div>
              <div className="bg-green-50 p-4 rounded-lg border border-green-100">
                <div className="text-green-600 text-sm font-medium mb-1">Patients</div>
                <div className="text-gray-900 font-semibold">{patientStats.total}</div>
              </div>
              <div className="bg-purple-50 p-4 rounded-lg border border-purple-100">
                <div className="text-purple-600 text-sm font-medium mb-1">Experience</div>
                <div className="text-gray-900 font-semibold">{doctor.years_experience || '5+ years'}</div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      {/* Tabs Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex">
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'profile'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('profile')}
          >
            Profile
          </button>
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'patients'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('patients')}
          >
            Patients
          </button>
          <button
            className={`py-2 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'schedule'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
            onClick={() => setActiveTab('schedule')}
          >
            Schedule
          </button>
        </nav>
      </div>

      {/* Profile Tab */}
      {activeTab === 'profile' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Professional Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Full Name</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{fullName}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Specialty</td>
                  <td className="px-4 py-2 text-sm">
                    <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                      {specialty}
                    </span>
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">License Number</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{licenseNumber}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Years of Experience</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.years_experience || '5+'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Education</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.education || 'MD, University Medical School'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Board Certification</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.certification || 'Board Certified'}</td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Contact Information">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Phone</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.phone || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Email</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.email || '-'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Office</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.office || 'Room 101'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Department</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.department || specialty}</td>
                </tr>
              </tbody>
            </table>
          </Card>

          <Card title="Working Hours">
            <table className="min-w-full divide-y divide-gray-200">
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Monday</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.work_hours?.monday || '8:00 AM - 5:00 PM'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Tuesday</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.work_hours?.tuesday || '8:00 AM - 5:00 PM'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Wednesday</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.work_hours?.wednesday || '8:00 AM - 5:00 PM'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Thursday</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.work_hours?.thursday || '8:00 AM - 5:00 PM'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Friday</td>
                  <td className="px-4 py-2 text-sm text-gray-700">{doctor.work_hours?.friday || '8:00 AM - 5:00 PM'}</td>
                </tr>
                <tr>
                  <td className="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">Weekend</td>
                  <td className="px-4 py-2 text-sm">
                    <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                      {doctor.work_hours?.weekend || 'Off duty'}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </Card>
        </div>
      )}

      {/* Patients Tab */}
      {activeTab === 'patients' && (
        <Card title="Doctor's Patients">
          {patientsLoading ? (
            <div className="py-8 text-center">
              <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mx-auto"></div>
              <p className="mt-2 text-gray-600">Loading patients...</p>
            </div>
          ) : patients.length === 0 ? (
            <div className="py-8 text-center text-gray-500">
              No patients found for this doctor
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {patients.map((patient, index) => {
                    // Since we might not have real patient data, we'll generate some for demo
                    const patientId = patient.id || index + 1;
                    const firstName = patient.first_name || `Patient`;
                    const lastName = patient.last_name || `${index + 1}`;
                    const visitDate = patient.last_visit_date || new Date(Date.now() - Math.random() * 10000000000).toISOString().split('T')[0];
                    
                    // Random status for visualization
                    const statuses = ['Active', 'Follow-up', 'Recovered', 'Pending'];
                    const statusColors = {
                      'Active': 'bg-green-100 text-green-800',
                      'Follow-up': 'bg-yellow-100 text-yellow-800',
                      'Recovered': 'bg-blue-100 text-blue-800',
                      'Pending': 'bg-gray-100 text-gray-800'
                    };
                    const status = patient.status || statuses[Math.floor(Math.random() * statuses.length)];
                    
                    return (
                      <tr key={patientId} className="hover:bg-gray-50">
                        <td className="px-4 py-3">
                          <div className="flex items-center">
                            <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-600 font-medium text-sm">
                              {firstName[0]}{lastName[0]}
                            </div>
                            <div>
                              <div className="text-sm font-medium text-gray-900">
                                {firstName} {lastName}
                              </div>
                              <div className="text-sm text-gray-500">
                                ID: {patientId}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-500">{visitDate}</td>
                        <td className="px-4 py-3">
                          <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[status]}`}>
                            {status}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-sm font-medium">
                          <div className="flex space-x-2">
                            <Link 
                              to={`/patients/${patientId}`} 
                              className="inline-flex items-center px-2.5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                              View
                            </Link>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {/* Schedule Tab */}
      {activeTab === 'schedule' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white p-6 rounded-lg shadow border border-gray-200">
              <div className="flex items-center">
                <div className="flex-shrink-0 p-3 rounded-md bg-blue-100">
                  <svg className="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Total Appointments</dt>
                    <dd className="flex items-baseline">
                      <div className="text-2xl font-semibold text-gray-900">{scheduleStats.totalAppointments}</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 rounded-lg shadow border border-gray-200">
              <div className="flex items-center">
                <div className="flex-shrink-0 p-3 rounded-md bg-yellow-100">
                  <svg className="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Upcoming Appointments</dt>
                    <dd className="flex items-baseline">
                      <div className="text-2xl font-semibold text-gray-900">{scheduleStats.upcomingAppointments}</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 rounded-lg shadow border border-gray-200">
              <div className="flex items-center">
                <div className="flex-shrink-0 p-3 rounded-md bg-green-100">
                  <svg className="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Completed Appointments</dt>
                    <dd className="flex items-baseline">
                      <div className="text-2xl font-semibold text-gray-900">{scheduleStats.completedAppointments}</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <Card title="Upcoming Appointments">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {/* Generate some mock appointments for display purposes */}
                  {Array.from({ length: 5 }).map((_, index) => {
                    // Generate random dates in the future for upcoming appointments
                    const today = new Date();
                    const futureDate = new Date();
                    futureDate.setDate(today.getDate() + Math.floor(Math.random() * 14) + 1);
                    
                    // Set random times
                    const hours = Math.floor(Math.random() * 8) + 9; // 9 AM to 5 PM
                    const minutes = [0, 15, 30, 45][Math.floor(Math.random() * 4)];
                    futureDate.setHours(hours, minutes);
                    
                    // Format date and time for display
                    const dateStr = futureDate.toISOString().split('T')[0];
                    const timeStr = futureDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    
                    // Random appointment purposes
                    const purposes = ['Checkup', 'Follow-up', 'Consultation', 'Lab Results', 'Treatment'];
                    const purpose = purposes[Math.floor(Math.random() * purposes.length)];
                    
                    // Random status
                    const statuses = ['Confirmed', 'Pending', 'Rescheduled'];
                    const statusColors = {
                      'Confirmed': 'bg-green-100 text-green-800',
                      'Pending': 'bg-yellow-100 text-yellow-800',
                      'Rescheduled': 'bg-blue-100 text-blue-800'
                    };
                    const status = statuses[Math.floor(Math.random() * statuses.length)];
                    
                    return (
                      <tr key={index} className="hover:bg-gray-50">
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="text-sm font-medium text-gray-900">{dateStr}</div>
                          <div className="text-sm text-gray-500">{timeStr}</div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center">
                            <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-600 font-medium text-sm">
                              P{index + 1}
                            </div>
                            <div>
                              <div className="text-sm font-medium text-gray-900">Patient {index + 1}</div>
                              <div className="text-sm text-gray-500">ID: {1000 + index}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-900">{purpose}</td>
                        <td className="px-4 py-3">
                          <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[status]}`}>
                            {status}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-sm font-medium">
                          <div className="flex space-x-2">
                            <button className="inline-flex items-center px-2.5 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100">
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              Confirm
                            </button>
                            <button className="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100">
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                              </svg>
                              Cancel
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}

      <div className="mt-4">
        <Link to="/doctors">
          <Button variant="secondary">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clipRule="evenodd" />
            </svg>
            Back to Doctors
          </Button>
        </Link>
      </div>
    </div>
  );
};

export default DoctorDetails;