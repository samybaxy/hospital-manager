import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import Card from '../../components/Card';
import Button from '../../components/Button';
import { api } from '../../services/apiService';

// CSS utility for line clamping
const lineClampStyle = {
  display: '-webkit-box',
  WebkitLineClamp: '2',
  WebkitBoxOrient: 'vertical',
  overflow: 'hidden'
};

// CSS animation for fadeout
const fadeOutAnimation = {
  '@keyframes fadeOut': {
    '0%': { opacity: 1 },
    '75%': { opacity: 1 },
    '100%': { opacity: 0 }
  },
  animation: 'fadeOut 5s forwards'
};

const DoctorDetails = () => {
  const { doctorId } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const [doctor, setDoctor] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [activeTab, setActiveTab] = useState(location.state?.activeTab || 'profile');
  const [patients, setPatients] = useState([]);
  const [patientsLoading, setPatientsLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [patientStats, setPatientStats] = useState({
    total: 0,
    active: 0,
    recentVisits: 0,
    last_page: 1
  });
  const [scheduleStats, setScheduleStats] = useState({
    totalAppointments: 0,
    upcomingAppointments: 0,
    completedAppointments: 0,
    pendingAppointments: 0,
    confirmedAppointments: 0
  });
  const [upcomingAppointments, setUpcomingAppointments] = useState([]);
  const [appointmentsLoading, setAppointmentsLoading] = useState(false);

  // State for patient-specific errors that won't affect the main doctor view
  const [patientsError, setPatientsError] = useState(null);

  // Check for success messages from edit form
  useEffect(() => {
    if (location.state?.success) {
      // Flash a success message briefly then clear it
      setSuccess(location.state.success);
      setTimeout(() => {
        setSuccess(null);
        // Clear the state so refreshing doesn't show the message again
        navigate(location.pathname, { replace: true });
      }, 5000);
    }
  }, [location.state, navigate, location.pathname]);

  // Helper function to format time from 24-hour to 12-hour format
  const formatTime = (timeString) => {
    if (!timeString) return 'N/A';
    
    // Handle different time formats (HH:MM, HH:MM:SS)
    let hours, minutes;
    
    if (typeof timeString === 'string' && timeString.includes(':')) {
      const timeParts = timeString.split(':');
      hours = parseInt(timeParts[0], 10);
      minutes = timeParts[1];
      
      // Ensure minutes is always two digits
      if (minutes && minutes.length === 1) {
        minutes = `0${minutes}`;
      }
    } else {
      return 'N/A';
    }
    
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
  };
  
  // Function to fetch doctor's patients when patients tab is clicked
  const fetchPatients = useCallback(async (page = 1) => {
    if (!doctorId) return;
    
    try {
      setPatientsLoading(true);
      setPatientsError(null);
      const response = await api.get(`/doctors/${doctorId}/patients?page=${page}&per_page=20`);
      
      if (response.data && response.data.data) {
        setPatients(response.data.data);
      } else if (response.data) {
        setPatients(response.data);
      } else {
        setPatients([]);
      }

      // Set patient statistics based on the API response
      // Only update the statistics if we don't already have a value or if the new one is different
      setPatientStats(prevStats => {
        // If we already have stats from initial load, keep the total count consistent
        return {
          total: response.data?.meta?.total || prevStats.total || 0,
          active: response.data?.meta?.active || prevStats.active || 0,
          recentVisits: response.data?.meta?.recent_visits || prevStats.recentVisits || 0,
          last_page: response.data?.meta?.last_page || 1,
          current_page: response.data?.meta?.current_page || page
        };
      });
    } catch (err) {
      console.error('Error fetching patients:', err);
      setPatientsError('Unable to load patients data. Please try again later.');
      setPatients([]);
    } finally {
      setPatientsLoading(false);
    }
  }, [doctorId]);

  // Function to fetch doctor's appointments
  const fetchAppointments = useCallback(async () => {
    if (!doctorId) return;
    
    try {
      setAppointmentsLoading(true);
      console.log('Fetching appointments for doctor:', doctorId);

      // Get all upcoming appointments (both pending and confirmed)
      const response = await api.get(`/appointments?doctor_id=${doctorId}&upcoming=true`);
      console.log('Appointments response:', response.data);
      
      if (response.data && response.data.success) {
        // Handle successful response with data structure
        const appointments = response.data.data || [];
        console.log('Found appointments:', appointments.length);
        setUpcomingAppointments(appointments);
        
        // Update stats based on the appointment data
        const pendingCount = appointments.filter(a => a.status === 'pending').length;
        const confirmedCount = appointments.filter(a => a.status === 'confirmed').length;
        
        setScheduleStats(prev => ({
          ...prev,
          upcomingAppointments: appointments.length,
          pendingAppointments: pendingCount,
          confirmedAppointments: confirmedCount,
          // Don't override totalAppointments from the upcoming appointments call
          // Keep the value from the stats endpoint which shows all appointments
        }));
      } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
        // Handle nested data structure
        console.log('Found nested data structure with appointments:', response.data.data.length);
        setUpcomingAppointments(response.data.data);
        
        const pendingCount = response.data.data.filter(a => a.status === 'pending').length;
        const confirmedCount = response.data.data.filter(a => a.status === 'confirmed').length;
        
        setScheduleStats(prev => ({
          ...prev,
          upcomingAppointments: response.data.data.length,
          pendingAppointments: pendingCount,
          confirmedAppointments: confirmedCount,
          totalAppointments: response.data.meta?.total || prev.totalAppointments
        }));
      } else if (response.data && Array.isArray(response.data)) {
        // Handle flat array response
        console.log('Found flat array response with appointments:', response.data.length);
        setUpcomingAppointments(response.data);
        
        const pendingCount = response.data.filter(a => a.status === 'pending').length;
        const confirmedCount = response.data.filter(a => a.status === 'confirmed').length;
        
        setScheduleStats(prev => ({
          ...prev,
          upcomingAppointments: response.data.length,
          pendingAppointments: pendingCount,
          confirmedAppointments: confirmedCount
        }));
      } else {
        console.log('No data found in response or unsupported format:', response.data);
        setUpcomingAppointments([]);
        // Set stats to 0 when no appointments found
        setScheduleStats(prev => ({
          ...prev,
          upcomingAppointments: 0,
          pendingAppointments: 0,
          confirmedAppointments: 0
        }));
      }
    } catch (err) {
      console.error('Error fetching appointments:', err);
      console.log('Error details:', {
        message: err.message,
        status: err.response?.status,
        data: err.response?.data
      });
      setUpcomingAppointments([]);
    } finally {
      setAppointmentsLoading(false);
    }
  }, [doctorId]);

  // Effect to fetch patients when tab changes to patients or page changes
  useEffect(() => {
    if (activeTab === 'patients') {
      fetchPatients(currentPage);
    }
  }, [activeTab, fetchPatients, currentPage]);

  // Effect to fetch appointments when tab changes to schedule
  useEffect(() => {
    if (activeTab === 'schedule' && upcomingAppointments.length === 0) {
      fetchAppointments();
    }
  }, [activeTab, fetchAppointments, upcomingAppointments.length]);

  useEffect(() => {
    const fetchDoctor = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/doctors/${doctorId}`);

        // Check for the structure of the response and extract the doctor data properly
        if (response.data && response.data.data) {
          // If the API returns nested data structure
          setDoctor(response.data.data);
        } else if (response.data) {
          // If the API returns flat data structure
          setDoctor(response.data);
        }

        // Initialize default schedule statistics
        setScheduleStats({
          totalAppointments: 0,
          upcomingAppointments: 0,
          completedAppointments: 0
        });

        // Fetch patient count and statistics early, regardless of tab
        try {
          const patientResponse = await api.get(`/doctors/${doctorId}/patients`);
          if (patientResponse.data) {
            // Set patient statistics based on the API response
            setPatientStats({
              total: patientResponse.data.meta?.total || 0,
              active: patientResponse.data.meta?.active || 0,
              recentVisits: patientResponse.data.meta?.recent_visits || 0
            });
          }
        } catch (patientErr) {
          console.warn('Could not fetch patient statistics:', patientErr);
        }

        // Fetch real appointment statistics
        try {
          const statsResponse = await api.get(`/appointments/stats?doctor_id=${doctorId}`);

          if (statsResponse.data) {
            console.log('Schedule stats received:', statsResponse.data);
            setScheduleStats({
              ...statsResponse.data,
              // Make sure we include both pending and confirmed appointments in upcomingAppointments count
              upcomingAppointments: (statsResponse.data.pendingAppointments || 0) + 
                                   (statsResponse.data.confirmedAppointments || 0)
            });
            console.log('Schedule stats set to:', {
              ...statsResponse.data,
              upcomingAppointments: (statsResponse.data.pendingAppointments || 0) + 
                                   (statsResponse.data.confirmedAppointments || 0)
            });
          }
        } catch (statsErr) {
          console.error('Could not fetch appointment statistics:', statsErr);
          console.log('Stats error details:', {
            message: statsErr.message,
            status: statsErr.response?.status,
            data: statsErr.response?.data
          });
          
          // Set default stats on error
          setScheduleStats(prev => ({
            totalAppointments: 0,
            upcomingAppointments: 0,
            completedAppointments: 0,
            pendingAppointments: 0,
            confirmedAppointments: 0,
            cancelledAppointments: 0
          }));
        }
      } catch (err) {
        console.error('Error fetching doctor details:', err);
        setError('Failed to load doctor details. The doctor may not exist or you may not have permission to view it.');
      } finally {
        setLoading(false);
      }
    };
    
    fetchDoctor();
  }, [doctorId]);

  // Check for success messages from edit form
  useEffect(() => {
    if (location.state?.success) {
      // Flash a success message briefly then clear it
      setSuccess(location.state.success);
      setTimeout(() => {
        setSuccess(null);
        // Clear the state so refreshing doesn't show the message again
        navigate(location.pathname, { replace: true });
      }, 5000);
    }
  }, [location.state, navigate, location.pathname]);

  const handleDelete = async () => {
    if (!window.confirm('Are you sure you want to delete this doctor? This action cannot be undone.')) {
      return;
    }

    try {
      setLoading(true);
      await api.delete(`/doctors/${doctorId}`);
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
            Doctor ID: {doctor.ID || '-'}
          </p>
        </div>
        <div className="flex gap-2 mt-2 md:mt-0">
          <Link to={`/doctors/${doctorId}/edit`}>
            <Button variant="primary">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
              </svg>
              Edit Doctor
            </Button>
          </Link>
          <Link to={`/appointments/book/${doctorId}`} state={{ 
            returnTo: 'doctor',
            returnPath: `/doctors/${doctorId}`,
            doctorName: `${doctor?.first_name} ${doctor?.last_name}`
          }}>
            <Button variant="success">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clipRule="evenodd" />
              </svg>
              Book Appointment
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

      {/* Success message */}
      {success && (
        <div className="mb-6 bg-green-50 p-4 rounded-md border border-green-200 text-green-700 flex items-center" style={fadeOutAnimation}>
          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {success}
        </div>
      )}

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
            <div className="space-y-3">
              {(() => {
                // Parse working hours from appointment_availability
                const parseWorkingHours = () => {
                  
                    if (!doctor.appointment_availability || 
                        doctor.appointment_availability === '' ||  
                        doctor.appointment_availability === null || 
                        doctor.appointment_availability === undefined) 
                    {
                        console.log('No valid appointment_availability found');
                        return null;
                    }

                  try {
                    let availabilityData;
                    
                    // Handle if it's already an object or if it's a JSON string
                    if (typeof doctor.appointment_availability === 'string') {
                        availabilityData = JSON.parse(doctor.appointment_availability);
                    } else if (typeof doctor.appointment_availability === 'object') {
                      console.log('Using object directly:', doctor.appointment_availability);
                      availabilityData = doctor.appointment_availability;
                    } else {
                      console.log('Unknown type for appointment_availability');
                      return null;
                    }

                    // Check if availabilityData is valid
                    if (!availabilityData || typeof availabilityData !== 'object') {
                        console.log('Invalid availability data after parsing');
                        return null;
                    }

                    // Convert backend format to display format
                    // Backend format: {day: [{start: "09:00", end: "17:00"}]} for enabled days
                    const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    const workingDays = [];
                    let hasAnyWorkingDay = false;
                    
                    dayOrder.forEach(day => {                      
                      if (availabilityData[day] && Array.isArray(availabilityData[day]) && availabilityData[day].length > 0) {
                        const schedule = availabilityData[day][0]; // Take first schedule
                        if (schedule && schedule.start && schedule.end) {
                          workingDays.push({
                            day: day.charAt(0).toUpperCase() + day.slice(1),
                            start: schedule.start,
                            end: schedule.end,
                            enabled: true
                          });
                          hasAnyWorkingDay = true;
                        } else {
                            console.log(`${day} schedule is invalid`);
                            workingDays.push({
                                day: day.charAt(0).toUpperCase() + day.slice(1),
                                enabled: false
                            });
                        }
                      } else {
                        workingDays.push({
                            day: day.charAt(0).toUpperCase() + day.slice(1),
                            enabled: false
                        });
                      }
                    });
                    
                    return hasAnyWorkingDay ? workingDays : null;
                  } catch (e) {
                    console.error('Error parsing working hours:', e);
                    return null;
                  }
                };

                const workingHours = parseWorkingHours();
                const hasWorkingHours = workingHours && workingHours.some(day => day.enabled);

                if (!hasWorkingHours) {
                  return (
                    <div className="text-center py-6 text-gray-500">
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <p className="text-sm">Working hours not set</p>
                      <Link to={`/doctors/${doctorId}/edit`} className="text-sm text-blue-600 hover:text-blue-800 mt-1 inline-block">
                        Set working hours
                      </Link>
                    </div>
                  );
                }

                return workingHours.map((daySchedule) => (
                  <div key={daySchedule.day} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                    <span className="text-sm font-medium text-gray-900">
                      {daySchedule.day}
                    </span>
                    <div className="flex items-center">
                      {daySchedule.enabled ? (
                        <span className="text-sm text-gray-700 bg-green-50 px-3 py-1 rounded-full border border-green-200">
                          {formatTime(daySchedule.start)} - {formatTime(daySchedule.end)}
                        </span>
                      ) : (
                        <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                          Off duty
                        </span>
                      )}
                    </div>
                  </div>
                ));
              })()}
            </div>
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
          ) : patientsError ? (
            <div className="py-8">
              <div className="bg-amber-50 p-4 rounded-md border border-amber-200 text-amber-700">
                <div className="flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {patientsError}
                </div>
                <button 
                  className="mt-3 text-sm font-medium text-amber-800 hover:text-amber-900 flex items-center"
                  onClick={() => fetchPatients()}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Try again
                </button>
              </div>
            </div>
          ) : patients.length === 0 ? (
            <div className="py-8 text-center text-gray-500">
              No patients found for this doctor
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit Date</th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Time</th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {patients.map((patient, index) => {
                      const patientId = patient.ID || index + 1;
                      const firstName = patient.first_name || `Patient`;
                      const lastName = patient.last_name || `${index + 1}`;
                      const visitDate = patient.last_visit_date || new Date(Date.now() - Math.random() * 10000000000).toISOString().split('T')[0];
                      const visitTime = patient.last_visit_time ? formatTime(patient.last_visit_time) : 'N/A';
                      
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
                            <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                              {visitTime}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-sm font-medium">
                            <div className="flex space-x-2">
                              <Link 
                                to={`/patients/${patientId}`} 
                                state={{ fromDoctor: { doctorId, name: fullName, specialty: specialty } }}
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
              
              {/* Pagination Controls */}
              {patientStats.total > 0 && (
                <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                  <div className="flex flex-1 justify-between sm:hidden">
                    <button
                      onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                      disabled={currentPage === 1}
                      className={`relative inline-flex items-center rounded-md border ${currentPage === 1 ? 'border-gray-300 bg-gray-100 text-gray-400' : 'border-gray-300 bg-white text-gray-700'} px-4 py-2 text-sm font-medium hover:bg-gray-50`}
                    >
                      Previous
                    </button>
                    <button
                      onClick={() => setCurrentPage(prev => prev < patientStats.last_page ? prev + 1 : prev)}
                      disabled={currentPage >= patientStats.last_page}
                      className={`relative ml-3 inline-flex items-center rounded-md border ${currentPage >= patientStats.last_page ? 'border-gray-300 bg-gray-100 text-gray-400' : 'border-gray-300 bg-white text-gray-700'} px-4 py-2 text-sm font-medium hover:bg-gray-50`}
                    >
                      Next
                    </button>
                  </div>
                  <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                      <p className="text-sm text-gray-700">
                        Showing <span className="font-medium">{((currentPage - 1) * 20) + 1}</span> to <span className="font-medium">{Math.min(currentPage * 20, patientStats.total)}</span> of{' '}
                        <span className="font-medium">{patientStats.total}</span> patients
                      </p>
                    </div>
                    <div>
                      <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <button
                          onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                          disabled={currentPage === 1}
                          className={`relative inline-flex items-center rounded-l-md px-2 py-2 ${currentPage === 1 ? 'text-gray-300' : 'text-gray-500 hover:bg-gray-50'}`}
                        >
                          <span className="sr-only">Previous</span>
                          <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                          </svg>
                        </button>
                        {/* Page numbers */}
                        {Array.from({ length: Math.min(5, patientStats.last_page) }).map((_, idx) => {
                          const pageNumber = idx + 1;
                          return (
                            <button
                              key={pageNumber}
                              onClick={() => setCurrentPage(pageNumber)}
                              className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${currentPage === pageNumber ? 'bg-primary-600 text-white focus-visible:outline-offset-2' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'}`}
                            >
                              {pageNumber}
                            </button>
                          );
                        })}
                        <button
                          onClick={() => setCurrentPage(prev => prev < patientStats.last_page ? prev + 1 : prev)}
                          disabled={currentPage >= patientStats.last_page}
                          className={`relative inline-flex items-center rounded-r-md px-2 py-2 ${currentPage >= patientStats.last_page ? 'text-gray-300' : 'text-gray-500 hover:bg-gray-50'}`}
                        >
                          <span className="sr-only">Next</span>
                          <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                          </svg>
                        </button>
                      </nav>
                    </div>
                  </div>
                </div>
              )}
            </>
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
                      <div className="text-xs text-gray-500 ml-2">(confirmed, pending, completed)</div>
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
            {appointmentsLoading ? (
              <div className="py-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mx-auto"></div>
                <p className="mt-2 text-gray-600">Loading appointments...</p>
              </div>
            ) : upcomingAppointments.length > 0 ? (
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
                    {upcomingAppointments.map((appointment) => {
                      // Format date for display
                      const formattedDate = appointment.date || appointment.appointment_date;
                      const formattedTime = appointment.time || appointment.appointment_time;
                      
                      // Define status color mappings
                      const statusColors = {
                        'confirmed': 'bg-green-100 text-green-800',
                        'pending': 'bg-yellow-100 text-yellow-800',
                        'rescheduled': 'bg-blue-100 text-blue-800',
                        'cancelled': 'bg-red-100 text-red-800',
                        'completed': 'bg-gray-100 text-gray-800'
                      };
                      
                      // Get status from appointment, default to pending if not set
                      const status = appointment.status || 'pending';
                      
                      return (
                        <tr key={appointment.ID} className="hover:bg-gray-50">
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">{formattedDate}</div>
                            <div className="text-sm text-gray-500">
                              {formattedTime ? (
                                formatTime(formattedTime)
                              ) : 'No time specified'}
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center">
                              <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-600 font-medium text-sm">
                                {appointment.patient_name ? appointment.patient_name.charAt(0) : 'P'}
                              </div>
                              <div>
                                <div className="text-sm font-medium text-gray-900">{appointment.patient_name || 'Unknown Patient'}</div>
                                <div className="text-sm text-gray-500">ID: {appointment.patient_id || 'N/A'}</div>
                              </div>
                            </div>
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-900">{appointment.reason || 'No reason specified'}</td>
                          <td className="px-4 py-3">
                            <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[status] || 'bg-gray-100 text-gray-800'}`}>
                              {status.charAt(0).toUpperCase() + status.slice(1)}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-sm font-medium">
                            <div className="flex space-x-2">
                              {status === 'pending' && (
                                <button 
                                  className="inline-flex items-center px-2.5 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100"
                                  onClick={() => {
                                    // Add logic to confirm appointment
                                    // You can call the API endpoint to update the appointment status
                                    api.put(`/appointments/${appointment.ID}`, { status: 'confirmed' })
                                      .then(() => fetchAppointments())
                                      .catch(err => console.error('Error confirming appointment:', err));
                                  }}
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>
                                  Confirm
                                </button>
                              )}
                              {(status === 'confirmed' || status === 'pending' || status === 'rescheduled') && (
                                <button 
                                  className="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100"
                                  onClick={() => {
                                    // Add logic to cancel appointment
                                    if (window.confirm('Are you sure you want to cancel this appointment?')) {
                                      api.put(`/appointments/${appointment.ID}`, { status: 'cancelled' })
                                        .then(() => fetchAppointments())
                                        .catch(err => console.error('Error cancelling appointment:', err));
                                    }
                                  }}
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                  </svg>
                                  Cancel
                                </button>
                              )}
                              <Link to={`/appointments/${appointment.ID}`} state={{ returnTo: 'doctor', returnPath: `/doctors/${doctorId}`, doctorName: fullName }}>
                                <button className="inline-flex items-center px-2.5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100">
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                  </svg>
                                  View
                                </button>
                              </Link>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p className="text-gray-600 mb-2">No upcoming appointments found</p>
                <p className="text-sm text-gray-500 mb-4">This doctor currently has no scheduled appointments.</p>
                <button 
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                  onClick={fetchAppointments}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Refresh
                </button>
              </div>
            )}
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