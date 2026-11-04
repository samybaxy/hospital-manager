import React from 'react';

/**
 * Catches render errors in a page so one broken screen doesn't blank the whole
 * app. Resets when the route changes (resetKey).
 */
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidUpdate(prevProps) {
    if (this.state.error && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ error: null });
    }
  }

  componentDidCatch(error, info) {
    // eslint-disable-next-line no-console
    console.error('Page error:', error, info && info.componentStack);
  }

  render() {
    if (this.state.error) {
      return (
        <div className="max-w-xl mx-auto my-12 p-6 bg-white rounded-lg shadow border border-red-200">
          <h2 className="text-lg font-semibold text-red-700 mb-2">Something went wrong on this page</h2>
          <p className="text-gray-600 mb-4">
            The rest of the app still works. Try again, or go back to the dashboard.
          </p>
          <div className="flex gap-3">
            <button
              type="button"
              className="px-4 py-2 rounded bg-primary-600 text-white hover:bg-primary-700"
              onClick={() => this.setState({ error: null })}
            >
              Try again
            </button>
            <a href="#/" className="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
              Dashboard
            </a>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

export default ErrorBoundary;
