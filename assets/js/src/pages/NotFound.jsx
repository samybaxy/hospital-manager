import React from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { Link } from 'react-router-dom';

const NotFound = () => {
  return (
    <div className="flex items-center justify-center min-h-[50vh]">
      <Card>
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-800">404</h1>
          <h2 className="text-2xl font-semibold text-gray-700 mt-2">Page Not Found</h2>
          <p className="text-gray-600 mt-4">
            The page you are looking for does not exist or has been moved.
          </p>
          <div className="mt-6">
            <Link to="/">
              <Button variant="primary">Return to Dashboard</Button>
            </Link>
          </div>
        </div>
      </Card>
    </div>
  );
};

export default NotFound;
