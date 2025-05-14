import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import authService from '../services/authService';

const PasswordResetConfirm = () => {
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  
  // Get token from URL params
  const token = searchParams.get('token');
  
  useEffect(() => {
    if (!token) {
      setMessage('Invalid or missing reset token. Please request a new password reset.');
    }
  }, [token]);
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!token) {
      setMessage('Invalid or missing reset token. Please request a new password reset.');
      setIsSuccess(false);
      return;
    }
    
    if (!password) {
      setMessage('Please enter a new password');
      setIsSuccess(false);
      return;
    }
    
    if (password !== confirmPassword) {
      setMessage('Passwords do not match');
      setIsSuccess(false);
      return;
    }
    
    // Password strength validation
    if (password.length < 8) {
      setMessage('Password must be at least 8 characters long');
      setIsSuccess(false);
      return;
    }
    
    setIsSubmitting(true);
    setMessage('');
    
    try {
      const response = await authService.completePasswordReset(token, password);
      setMessage(response.data.message || 'Password has been reset successfully');
      setIsSuccess(true);
      
      // Redirect to login after a delay
      setTimeout(() => {
        navigate('/login');
      }, 3000);
    } catch (err) {
      console.error('Password reset error:', err);
      setMessage(err.response?.data?.message || 'Failed to reset password. Please try again.');
      setIsSuccess(false);
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-b from-primary-50 to-primary-100">
      <div className="max-w-md w-full p-8 bg-white rounded-lg shadow-xl">
        <div className="text-center mb-6">
          <h2 className="text-2xl font-bold text-primary-800">Reset Your Password</h2>
          <p className="text-gray-600 mt-2">Enter your new password below</p>
        </div>
        
        {message && (
          <div className={`mb-6 p-4 rounded-md ${
            isSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
          }`}>
            {message}
          </div>
        )}
        
        {!token && (
          <div className="text-center mt-4">
            <button
              type="button"
              className="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              onClick={() => navigate('/login')}
            >
              Return to Login
            </button>
          </div>
        )}
        
        {token && !isSuccess && (
          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                New Password
              </label>
              <input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Enter your new password"
                disabled={isSubmitting}
                required
              />
            </div>
            
            <div>
              <label htmlFor="confirm-password" className="block text-sm font-medium text-gray-700 mb-1">
                Confirm New Password
              </label>
              <input
                id="confirm-password"
                type="password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Confirm your new password"
                disabled={isSubmitting}
                required
              />
            </div>
            
            <div>
              <button
                type="submit"
                className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150"
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Resetting Password...' : 'Reset Password'}
              </button>
            </div>
            
            <div className="text-center">
              <button
                type="button"
                className="text-sm text-primary-600 hover:text-primary-800"
                onClick={() => navigate('/login')}
              >
                Return to Login
              </button>
            </div>
          </form>
        )}
        
        {isSuccess && (
          <div className="text-center mt-4">
            <p className="text-sm text-gray-600 mb-4">
              You will be redirected to the login page in a few seconds...
            </p>
            <button
              type="button"
              className="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              onClick={() => navigate('/login')}
            >
              Go to Login
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default PasswordResetConfirm;
