import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import authService from '../services/authService';

const PasswordResetRequest = ({ onClose }) => {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!email) {
      setMessage('Please enter your email address');
      setIsSuccess(false);
      return;
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      setMessage('Please enter a valid email address');
      setIsSuccess(false);
      return;
    }
    
    setIsSubmitting(true);
    setMessage('');
    
    try {
      const response = await authService.requestPasswordReset(email);
      setMessage(response.data.message || 'Password reset instructions have been sent to your email');
      setIsSuccess(true);
    } catch (err) {
      console.error('Password reset request error:', err);
      setMessage(err.response?.data?.message || 'Failed to request password reset. Please try again.');
      setIsSuccess(false);
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-medium text-gray-700">Reset Password</h2>
        <button 
          type="button" 
          className="text-gray-400 hover:text-gray-500"
          onClick={onClose}
        >
          <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
          </svg>
        </button>
      </div>
      
      {message && (
        <div className={`mb-4 p-3 rounded-md ${
          isSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
        }`}>
          {message}
        </div>
      )}
      
      {!isSuccess ? (
        <form onSubmit={handleSubmit}>
          <p className="mb-4 text-sm text-gray-600">
            Enter your email address and we'll send you instructions to reset your password.
          </p>
          
          <div className="mb-4">
            <label htmlFor="reset-email" className="block text-sm font-medium text-gray-700 mb-1">
              Email Address
            </label>
            <input
              id="reset-email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="your@email.com"
              disabled={isSubmitting}
              required
            />
          </div>
          
          <div className="flex justify-end">
            <button
              type="button"
              className="mr-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              onClick={onClose}
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'Sending...' : 'Send Reset Link'}
            </button>
          </div>
        </form>
      ) : (
        <div className="mt-4 mb-2 text-center">
          <button
            type="button"
            className="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
            onClick={onClose}
          >
            Close
          </button>
        </div>
      )}
    </div>
  );
};

export default PasswordResetRequest;
