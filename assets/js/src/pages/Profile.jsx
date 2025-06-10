import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useUserAccess } from '../hooks/useUserAccess';
import Card from '../components/Card';
import Button from '../components/Button';
import Modal from '../components/Modal';
import StatusMessage from '../components/StatusMessage';

const Profile = () => {
  const { user, updateUser } = useAuth();
  const { role, isPatient, isDoctor, isAdministrator } = useUserAccess();
  
  const [profileData, setProfileData] = useState(null);
  
  const [isEditing, setIsEditing] = useState(false);
  const [loading, setLoading] = useState(false);
  const [profileLoading, setProfileLoading] = useState(true);
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [statusMessage, setStatusMessage] = useState({
    message: '',
    type: '',
    show: false
  });
  
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: ''
  });
  
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    new_password: '',
    confirm_password: ''
  });

  useEffect(() => {
    fetchProfileData();
  }, [user]);

  const fetchProfileData = async () => {
    if (!user) return;
    
    setProfileLoading(true);
    try {
      const response = await fetch('/wp-json/hospital-manager/v1/profile', {
        headers: {
          'X-WP-Nonce': window.hospitalManager?.nonce || '',
        },
      });
      
      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setProfileData(data.data);
          setFormData({
            first_name: data.data.first_name || '',
            last_name: data.data.last_name || '',
            email: data.data.email || '',
            phone: data.data.phone || ''
          });
        }
      }
    } catch (error) {
      console.error('Error fetching profile data:', error);
      showStatusMessage('Failed to load profile data.', 'error');
    } finally {
      setProfileLoading(false);
    }
  };

  useEffect(() => {
    if (user && !profileData) {
      setFormData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email || '',
        phone: user.phone || ''
      });
    }
  }, [user, profileData]);

  const showStatusMessage = (message, type) => {
    setStatusMessage({
      message,
      type,
      show: true
    });
    setTimeout(() => {
      setStatusMessage(prev => ({ ...prev, show: false }));
    }, 5000);
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    try {
      const response = await fetch('/wp-json/hospital-manager/v1/profile', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.hospitalManager?.nonce || '',
        },
        body: JSON.stringify(formData),
      });
      
      const data = await response.json();
      
      if (response.ok && data.success) {
        setIsEditing(false);
        setProfileData(data.data);
        showStatusMessage('Profile updated successfully!', 'success');
        
        // Update user context if available
        if (updateUser) {
          updateUser(data.data);
        }
      } else {
        showStatusMessage(data.message || 'Failed to update profile. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Error updating profile:', error);
      showStatusMessage('Failed to update profile. Please try again.', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    
    if (passwordData.new_password !== passwordData.confirm_password) {
      showStatusMessage('New passwords do not match.', 'error');
      return;
    }
    
    if (passwordData.new_password.length < 8) {
      showStatusMessage('Password must be at least 8 characters long.', 'error');
      return;
    }
    
    // Additional password strength checks
    if (!/[A-Z]/.test(passwordData.new_password)) {
      showStatusMessage('Password must contain at least one uppercase letter.', 'error');
      return;
    }
    
    if (!/[a-z]/.test(passwordData.new_password)) {
      showStatusMessage('Password must contain at least one lowercase letter.', 'error');
      return;
    }
    
    if (!/[0-9]/.test(passwordData.new_password)) {
      showStatusMessage('Password must contain at least one number.', 'error');
      return;
    }
    
    if (passwordData.new_password === passwordData.current_password) {
      showStatusMessage('New password must be different from current password.', 'error');
      return;
    }
    
    setLoading(true);
    
    try {
      // Call the password change API
      const response = await api.post('/user/change-password', {
        current_password: passwordData.current_password,
        new_password: passwordData.new_password,
        nonce: authService.getCsrfToken()
      });
      
      if (response.data.success) {
        setShowPasswordModal(false);
        setPasswordData({
          current_password: '',
          new_password: '',
          confirm_password: ''
        });
        showStatusMessage('Password changed successfully!', 'success');
        
        // Update CSRF token if provided
        if (response.data.fresh_nonce) {
          authService.updateCsrfToken(response.data.fresh_nonce);
        }
      } else {
        showStatusMessage(response.data.message || 'Failed to change password. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Password change error:', error);
      
      let errorMessage = 'Failed to change password. Please try again.';
      if (error.response && error.response.data && error.response.data.message) {
        errorMessage = error.response.data.message;
      }
      
      showStatusMessage(errorMessage, 'error');
    } finally {
      setLoading(false);
    }
  };

  const getRoleBadgeColor = (role) => {
    const colors = {
      administrator: 'bg-purple-100 text-purple-800 border-purple-200',
      doctor: 'bg-blue-100 text-blue-800 border-blue-200',
      patient: 'bg-green-100 text-green-800 border-green-200',
      lab_tech: 'bg-orange-100 text-orange-800 border-orange-200',
      desk_officer: 'bg-indigo-100 text-indigo-800 border-indigo-200'
    };
    return colors[role] || 'bg-gray-100 text-gray-800 border-gray-200';
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'Not available';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      {/* Loading State */}
      {profileLoading && (
        <div className="flex justify-center items-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600">Loading profile...</span>
        </div>
      )}

      {!profileLoading && (
        <>
          {/* Status Message */}
          {statusMessage.show && (
            <StatusMessage
              message={statusMessage.message}
              type={statusMessage.type}
              onDismiss={() => setStatusMessage(prev => ({ ...prev, show: false }))}
            />
          )}

      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mx-4 md:mx-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div className="flex items-center space-x-4">
            <div className="h-16 w-16 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
              <span className="text-2xl font-bold text-white">
                {(profileData?.first_name && profileData?.last_name) ? 
                  `${profileData.first_name[0]}${profileData.last_name[0]}`.toUpperCase() :
                  (user?.name ? 
                    user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() 
                    : 'U')}
              </span>
            </div>
            <div>
              <h1 className="text-2xl font-bold">
                {(profileData?.first_name || profileData?.last_name) ?
                  `${profileData.first_name || ''} ${profileData.last_name || ''}`.trim() :
                  (user?.name || `${formData.first_name} ${formData.last_name}`.trim() || 'User Profile')}
              </h1>
              <div className="flex items-center space-x-2 mt-1">
                <span className={`px-3 py-1 rounded-full text-sm font-medium border ${getRoleBadgeColor(role)}`}>
                  {role?.replace('_', ' ').toUpperCase() || 'USER'}
                </span>
                <span className="text-blue-100">•</span>
                <span className="text-blue-100 text-sm">
                  Member since {formatDate(profileData?.user_registered || user?.created_at)}
                </span>
              </div>
            </div>
          </div>
          <div className="mt-4 md:mt-0">
            {!isEditing ? (
              <Button
                variant="secondary"
                onClick={() => setIsEditing(true)}
                className="bg-white bg-opacity-20 hover:bg-opacity-30 text-white border-white border-opacity-30"
              >
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Profile
              </Button>
            ) : (
              <div className="space-x-2">
                <Button
                  variant="secondary"
                  onClick={() => setIsEditing(false)}
                  className="bg-white bg-opacity-20 hover:bg-opacity-30 text-white border-white border-opacity-30"
                >
                  Cancel
                </Button>
              </div>
            )}
          </div>
        </div>
      </div>

      <div className="space-y-6">
        {/* Main Profile Information */}
        <div className="w-full">
          <Card className="p-8 shadow-sm border-0 bg-white">
            <div className="flex items-center justify-between mb-8">
              <div className="flex items-center space-x-3">
                <div className="h-2 w-2 bg-blue-500 rounded-full"></div>
                <h2 className="text-2xl font-bold text-gray-900">Personal Information</h2>
              </div>
            </div>

            {isEditing ? (
              <form onSubmit={handleSubmit} className="space-y-8">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                    <label className="block text-sm font-bold text-gray-700 mb-3">
                      First Name
                    </label>
                    <input
                      type="text"
                      name="first_name"
                      value={formData.first_name}
                      onChange={handleInputChange}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 focus:bg-white transition-colors"
                      placeholder="Enter your first name"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-bold text-gray-700 mb-3">
                      Last Name
                    </label>
                    <input
                      type="text"
                      name="last_name"
                      value={formData.last_name}
                      onChange={handleInputChange}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 focus:bg-white transition-colors"
                      placeholder="Enter your last name"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-bold text-gray-700 mb-3">
                    Email Address
                  </label>
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 focus:bg-white transition-colors"
                    placeholder="Enter your email address"
                  />
                </div>

                <div>
                  <label className="block text-sm font-bold text-gray-700 mb-3">
                    Phone Number
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 focus:bg-white transition-colors"
                    placeholder="Enter your phone number"
                  />
                </div>

                <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => setIsEditing(false)}
                    disabled={loading}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    variant="primary"
                    disabled={loading}
                  >
                    {loading ? 'Saving...' : 'Save Changes'}
                  </Button>
                </div>
              </form>
            ) : (
              <div className="space-y-8">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div className="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <label className="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">
                      First Name
                    </label>
                    <p className="text-lg font-semibold text-gray-900">
                      {formData.first_name || 'Not specified'}
                    </p>
                  </div>
                  <div className="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <label className="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">
                      Last Name
                    </label>
                    <p className="text-lg font-semibold text-gray-900">
                      {formData.last_name || 'Not specified'}
                    </p>
                  </div>
                </div>

                <div className="bg-gray-50 p-6 rounded-xl border border-gray-200">
                  <label className="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">
                    Email Address
                  </label>
                  <p className="text-lg font-semibold text-gray-900">
                    {formData.email || 'Not specified'}
                  </p>
                </div>

                <div className="bg-gray-50 p-6 rounded-xl border border-gray-200">
                  <label className="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">
                    Phone Number
                  </label>
                  <p className="text-lg font-semibold text-gray-900">
                    {formData.phone || 'Not specified'}
                  </p>
                </div>
              </div>
            )}
          </Card>
        </div>

        {/* Sidebar Cards */}
        <div className="w-full space-y-6">
          {/* Account Security */}
          <Card className="p-6 shadow-sm border-0 bg-gradient-to-br from-red-50 to-pink-50 border border-red-100">
            <div className="flex items-center space-x-2 mb-6">
              <div className="h-8 w-8 bg-red-100 rounded-lg flex items-center justify-center">
                <svg className="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 className="text-lg font-bold text-gray-900">Account Security</h3>
            </div>
            <div className="space-y-4">
              <div className="bg-white p-4 rounded-lg border border-red-200 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-semibold text-gray-900">Password</p>
                    <p className="text-xs text-gray-500 mt-1">Last changed 30 days ago</p>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowPasswordModal(true)}
                    className="bg-red-50 border-red-200 text-red-700 hover:bg-red-100"
                  >
                    Change
                  </Button>
                </div>
              </div>
              
              <div className="bg-white p-4 rounded-lg border border-red-200 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-semibold text-gray-900">Two-Factor Auth</p>
                    <p className="text-xs text-gray-500 mt-1">Not enabled</p>
                  </div>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    disabled
                    className="bg-gray-50 border-gray-200 text-gray-400"
                  >
                    Enable
                  </Button>
                </div>
              </div>
            </div>
          </Card>

          {/* Account Stats */}
          <Card className="p-6 shadow-sm border-0 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100">
            <div className="flex items-center space-x-2 mb-6">
              <div className="h-8 w-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg className="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <h3 className="text-lg font-bold text-gray-900">Account Activity</h3>
            </div>
            <div className="space-y-3">
              <div className="bg-white p-3 rounded-lg border border-blue-200 shadow-sm">
                <div className="flex justify-between items-center">
                  <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Account Created</span>
                  <span className="text-sm font-bold text-gray-900">
                    {formatDate(profileData?.user_registered || user?.created_at)}
                  </span>
                </div>
              </div>
              <div className="bg-white p-3 rounded-lg border border-blue-200 shadow-sm">
                <div className="flex justify-between items-center">
                  <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">User ID</span>
                  <span className="text-sm font-bold text-gray-900 font-mono">
                    {profileData?.ID || user?.ID || 'N/A'}
                  </span>
                </div>
              </div>
              <div className="bg-white p-3 rounded-lg border border-blue-200 shadow-sm">
                <div className="flex justify-between items-center">
                  <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Role</span>
                  <span className={`text-sm font-bold px-2 py-1 rounded-full ${getRoleBadgeColor(role)} border`}>
                    {role?.replace('_', ' ').toUpperCase() || 'USER'}
                  </span>
                </div>
              </div>
            </div>
          </Card>

          {/* Role-specific Information */}
          {isDoctor() && (
            <Card className="p-6 shadow-sm border-0 bg-gradient-to-br from-green-50 to-emerald-50 border border-green-100">
              <div className="flex items-center space-x-2 mb-6">
                <div className="h-8 w-8 bg-green-100 rounded-lg flex items-center justify-center">
                  <svg className="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                  </svg>
                </div>
                <h3 className="text-lg font-bold text-gray-900">Doctor Information</h3>
              </div>
              <div className="space-y-3">
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">License Number</span>
                    <span className="text-sm font-bold text-gray-900 font-mono">
                      {profileData?.license_number || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Specialization</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.specialty || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Office</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.office || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Status</span>
                    <span className={`text-xs font-bold px-2 py-1 rounded-full ${
                      profileData?.status === 'active' ? 'bg-green-100 text-green-800 border border-green-200' : 
                      'bg-gray-100 text-gray-800 border border-gray-200'
                    }`}>
                      {profileData?.status || 'Active'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Experience</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.years_experience ? `${profileData.years_experience} years` : 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-green-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Education</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.education || 'Not specified'}
                    </span>
                  </div>
                </div>
              </div>
            </Card>
          )}

          {isPatient() && (
            <Card className="p-6 shadow-sm border-0 bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-100">
              <div className="flex items-center space-x-2 mb-6">
                <div className="h-8 w-8 bg-purple-100 rounded-lg flex items-center justify-center">
                  <svg className="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <h3 className="text-lg font-bold text-gray-900">Patient Information</h3>
              </div>
              <div className="space-y-3">
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Patient ID</span>
                    <span className="text-sm font-bold text-gray-900 font-mono">
                      {profileData?.ID ? `PT-${profileData.ID.toString().padStart(3, '0')}` : 'Not assigned'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Age</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.age || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Gender</span>
                    <span className="text-sm font-bold text-gray-900 capitalize">
                      {profileData?.gender || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">Marital Status</span>
                    <span className="text-sm font-bold text-gray-900 capitalize">
                      {profileData?.marital_status || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">City</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.city || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">State</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.state || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">HMO</span>
                    <span className="text-sm font-bold text-gray-900">
                      {profileData?.hmo_name || 'Not specified'}
                    </span>
                  </div>
                </div>
                <div className="bg-white p-3 rounded-lg border border-purple-200 shadow-sm">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">HMO ID</span>
                    <span className="text-sm font-bold text-gray-900 font-mono">
                      {profileData?.hmo_designated_id || 'Not specified'}
                    </span>
                  </div>
                </div>
              </div>
            </Card>
          )}
        </div>
      </div>

      {/* Password Change Modal */}
      <Modal 
        isOpen={showPasswordModal} 
        onClose={() => setShowPasswordModal(false)}
        title="Change Password"
      >
        <form onSubmit={handlePasswordSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Current Password
            </label>
            <input
              type="password"
              name="current_password"
              value={passwordData.current_password}
              onChange={handlePasswordChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              New Password
            </label>
            <input
              type="password"
              name="new_password"
              value={passwordData.new_password}
              onChange={handlePasswordChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              required
              minLength={8}
            />
            <p className="text-xs text-gray-500 mt-1">
              Password must be at least 8 characters with uppercase, lowercase, and number
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Confirm New Password
            </label>
            <input
              type="password"
              name="confirm_password"
              value={passwordData.confirm_password}
              onChange={handlePasswordChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              required
            />
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowPasswordModal(false)}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={loading}
            >
              {loading ? 'Changing...' : 'Change Password'}
            </Button>
          </div>
        </form>
      </Modal>
        </>
      )}
    </div>
  );
};

export default Profile;
