import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { api } from '../services/apiClient';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  
  // Get the redirect path from location state or default to dashboard
  const from = location.state?.from?.pathname || '/';

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!email || !password) {
      setLoginError('Email and password are required');
      return;
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      setLoginError('Please enter a valid email address');
      return;
    }
    
    setIsSubmitting(true);
    setLoginError('');
    
    try {
      // Call the login endpoint directly using the exact path specified
      const response = await api.post('/auth/login', {
        username: email, // The backend expects 'username'
        password: password
      });
      
      if (response.data && response.data.authenticated) {
        // Store the token if provided by the API
        if (response.data.token) {
          // Store token in localStorage or sessionStorage based on rememberMe option
          if (rememberMe) {
            localStorage.setItem('hospital_manager_token', response.data.token);
          } else {
            sessionStorage.setItem('hospital_manager_token', response.data.token);
          }
          
          // Update the Authorization header for future requests
          api.defaults = {
            ...api.defaults,
            headers: {
              ...api.defaults?.headers,
              'Authorization': `Bearer ${response.data.token}`
            }
          };
        }
        
        // Update the auth context
        await login(email, password);
        
        // Redirect to the page they were trying to access or dashboard
        navigate(from, { replace: true });
      } else {
        setLoginError(response.data?.message || 'Invalid login credentials');
      }
    } catch (err) {
      console.error('Login error:', err);
      
      if (err.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        if (err.response.status === 401) {
          setLoginError('Invalid email or password. Please try again.');
        } else if (err.response.status === 403) {
          setLoginError('Your account does not have permission to access this system.');
        } else if (err.response.status === 429) {
          setLoginError('Too many login attempts. Please try again later.');
        } else {
          setLoginError(err.response?.data?.message || 'Login failed. Please try again.');
        }
      } else if (err.request) {
        // The request was made but no response was received
        setLoginError('Network error. Please check your connection and try again.');
      } else {
        // Something happened in setting up the request that triggered an Error
        setLoginError('An error occurred. Please try again later.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-b from-primary-50 to-primary-100">
      <div className="max-w-md w-full p-8 bg-white rounded-lg shadow-xl">
        <div className="text-center mb-8">
          <h2 className="text-3xl font-bold text-primary-800">Hospital Manager</h2>
          <p className="text-gray-600 mt-2">Sign in to your account</p>
        </div>
        
        {loginError && (
          <div className="mb-6 p-4 bg-red-50 text-red-700 rounded-md border border-red-200 flex items-start">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            <span>{loginError}</span>
          </div>
        )}
        
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label 
              htmlFor="email" 
              className="block text-sm font-medium text-gray-700 mb-1"
            >
              Email Address
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
              </div>
              <input
                id="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="user@example.com"
                disabled={isSubmitting}
                autoFocus
                required
              />
            </div>
          </div>
          
          <div>
            <div className="flex items-center justify-between mb-1">
              <label 
                htmlFor="password" 
                className="block text-sm font-medium text-gray-700"
              >
                Password
              </label>
              <a 
                href="#" 
                className="text-sm text-primary-600 hover:text-primary-800"
                onClick={(e) => {
                  e.preventDefault();
                  alert('Password reset functionality not implemented yet.');
                }}
              >
                Forgot password?
              </a>
            </div>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                </svg>
              </div>
              <input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="••••••••••••"
                disabled={isSubmitting}
                required
              />
            </div>
          </div>
          
          <div className="flex items-center">
            <input
              id="remember-me"
              name="remember-me"
              type="checkbox"
              checked={rememberMe}
              onChange={(e) => setRememberMe(e.target.checked)}
              className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
            />
            <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-700">
              Remember me
            </label>
          </div>
          
          <div>
            <button
              type="submit"
              className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Signing in...
                </>
              ) : 'Sign In'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default Login;
