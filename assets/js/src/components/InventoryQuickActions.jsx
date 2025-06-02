import React, { useState, useEffect } from 'react';
import Card from './Card';
import Button from './Button';
import inventoryService from '../services/inventoryService';
import LoadingState from './LoadingState';

const InventoryQuickActions = ({ onReload }) => {
  const [loading, setLoading] = useState(true);
  const [critical, setCritical] = useState([]);
  const [expiring, setExpiring] = useState([]);
  const [summary, setSummary] = useState(null);

  // Load data on component mount
  useEffect(() => {
    async function loadData() {
      setLoading(true);
      try {
        // Fetch summary data directly (this is what Inventory.jsx uses)
        const summaryData = await inventoryService.getSummary();
        setSummary(summaryData);
        
        // Fetch critical items
        const criticalItems = await inventoryService.getCriticalItems();
        
        // Ensure criticalItems is an array
        const validItems = Array.isArray(criticalItems) ? criticalItems : [];
        setCritical(validItems);
        
        // Fetch expiring items (next 30 days)
        const expiringItems = await inventoryService.getExpiringItems(30);
        
        // Ensure expiringItems is an array
        const validExpiringItems = Array.isArray(expiringItems) ? expiringItems : [];
        setExpiring(validExpiringItems);
        
      } catch (error) {
        console.error('Error loading quick actions data:', error);
      } finally {
        setLoading(false);
      }
    }
    
    loadData();
  }, []);

  // Get total count of active items (excluding out-of-stock)
  const totalActiveItems = summary ? (summary.total_items - summary.out_of_stock) : 0;
  
  // Get in stock count directly from summary
  // Make sure it's a number using parseInt to avoid type issues
  const inStockCount = summary ? parseInt(summary.in_stock || 0) : 0;
  
  // Get low stock count directly from summary
  const lowStockCount = summary ? parseInt(summary.low_stock || 0) : 0;
  
  // Get out of stock count directly from summary
  const outOfStockCount = summary ? parseInt(summary.out_of_stock || 0) : 0;
  
  // Quick filter buttons configuration
  const quickFilters = [
    { 
      id: 'low-stock',
      label: 'Low Stock', 
      description: 'Items below reorder level',
      filter: { low_stock: true },
      count: lowStockCount,
      variant: 'warning'
    },
    { 
      id: 'out-of-stock',
      label: 'Out of Stock', 
      description: 'Items with zero quantity',
      filter: { status: 'Out of Stock' },
      count: outOfStockCount,
      variant: 'danger'
    },
    { 
      id: 'expiring-soon',
      label: 'Expiring Soon', 
      description: 'Items expiring in 30 days',
      filter: { expiring: true },
      count: expiring.length,
      variant: 'warning'
    },
    { 
      id: 'active-items',
      label: 'All Active Items', 
      description: 'Items with quantity > 0',
      filter: { status: 'In Stock' },
      count: totalActiveItems,
      variant: 'primary'
    },
    { 
      id: 'currently-in-stock',
      label: 'Currently in Stock', 
      description: 'Healthy stock levels',
      filter: { status: 'In Stock', low_stock: false },
      count: inStockCount,
      variant: 'success'
    }
  ];

  if (loading) {
    return (
      <Card title="Quick Actions">
        <LoadingState message="Loading..." />
      </Card>
    );
  }

  return (
    <Card title="Quick Actions">
      <div className="space-y-4">
        <p className="text-sm text-gray-600">
          Quick filters to view specific inventory conditions
        </p>
        
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {quickFilters.map((filter) => (
            <div key={filter.id} className="bg-white border rounded-md p-4 shadow-sm hover:shadow transition-shadow">
              <h3 className="font-medium">{filter.label}</h3>
              <p className="text-sm text-gray-600 mt-1">{filter.description}</p>
              
              {filter.count !== undefined && (
                <div className="mt-2 mb-3">
                  <span className={`inline-block px-2 py-1 text-xs font-semibold rounded-full ${
                    filter.variant === 'danger' ? 'bg-red-100 text-red-800' :
                    filter.variant === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                    filter.variant === 'success' ? 'bg-green-100 text-green-800' :
                    filter.variant === 'primary' ? 'bg-blue-100 text-blue-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {filter.count} items
                  </span>
                </div>
              )}
              
              <Button
                variant={filter.variant || 'secondary'}
                size="sm"
                onClick={() => onReload(filter.filter)}
                className={`w-full mt-2 ${
                  filter.variant === 'warning' ? 'bg-yellow-500 hover:bg-yellow-600 text-white' :
                  filter.variant === 'danger' ? 'bg-red-500 hover:bg-red-600 text-white' :
                  filter.variant === 'success' ? 'bg-green-500 hover:bg-green-600 text-white' :
                  filter.variant === 'primary' ? 'bg-blue-500 hover:bg-blue-600 text-white' : 
                  'bg-gray-500 hover:bg-gray-600 text-white'
                }`}
              >
                View Items
              </Button>
            </div>
          ))}
        </div>
        
        {critical.length > 0 && (
          <div className="mt-6">
            <h3 className="text-sm font-medium mb-3">Critical Items</h3>
            <div className="bg-red-50 p-3 rounded-md border border-red-200">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                {critical.slice(0, 6).map((item, index) => (
                  <div key={`critical-${item.id || index}`} className="bg-white p-2 rounded border border-red-100 flex justify-between items-center">
                    <div>
                      <div className="font-medium">{item.item_name}</div>
                      <div className="text-xs text-gray-500">
                        {item.quantity > 0 ? `${item.quantity} ${item.unit} (Low)` : 'Out of stock!'}
                      </div>
                    </div>
                    
                    <Button
                      variant="link"
                      size="xs"
                      onClick={() => onReload({ search: item.item_name })}
                      className="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1 rounded"
                    >
                      Details
                    </Button>
                  </div>
                ))}
              </div>
              {critical.length > 6 && (
                <div className="text-center mt-3">
                  <Button 
                    variant="link" 
                    size="sm" 
                    onClick={() => onReload({ low_stock: true })}
                    className="text-red-600 hover:text-red-800 font-medium"
                  >
                    View all {critical.length} critical items
                  </Button>
                </div>
              )}
            </div>
          </div>
        )}
        
        {expiring.length > 0 && (
          <div className="mt-6">
            <h3 className="text-sm font-medium mb-3">Expiring Soon</h3>
            <div className="bg-yellow-50 p-3 rounded-md border border-yellow-200">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                {expiring.slice(0, 6).map((item, index) => (
                  <div key={`expiring-${item.id || index}`} className="bg-white p-2 rounded border border-yellow-100 flex justify-between items-center">
                    <div>
                      <div className="font-medium">{item.item_name}</div>
                      <div className="text-xs text-gray-500">
                        Expires: {new Date(item.expiry_date).toLocaleDateString()}
                      </div>
                    </div>
                    
                    <Button
                      variant="link"
                      size="xs"
                      onClick={() => onReload({ search: item.item_name })}
                      className="text-yellow-600 hover:text-yellow-700 bg-yellow-50 hover:bg-yellow-100 px-2 py-1 rounded"
                    >
                      Details
                    </Button>
                  </div>
                ))}
              </div>
              {expiring.length > 6 && (
                <div className="text-center mt-3">
                  <Button 
                    variant="link" 
                    size="sm" 
                    onClick={() => onReload({ expiring: true })}
                    className="text-yellow-600 hover:text-yellow-800 font-medium"
                  >
                    View all {expiring.length} expiring items
                  </Button>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </Card>
  );
};

export default InventoryQuickActions;
