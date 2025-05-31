import React, { useState, useEffect } from 'react';
import Card from './Card';
import Button from './Button';
import inventoryService from '../services/inventoryService';

const InventoryQuickActions = ({ onReload }) => {
  const [criticalItems, setCriticalItems] = useState([]);
  const [expiringItems, setExpiringItems] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadQuickData();
  }, []);

  const loadQuickData = async () => {
    setLoading(true);
    try {
      const [critical, expiring] = await Promise.all([
        inventoryService.getCriticalItems(),
        inventoryService.getExpiringItems(30)
      ]);
      
      setCriticalItems(critical.slice(0, 5)); // Show only top 5
      setExpiringItems(expiring.slice(0, 5)); // Show only top 5
    } catch (error) {
      console.error('Error loading quick data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <Card title="Quick Actions">
        <div className="text-center py-4">Loading...</div>
      </Card>
    );
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Critical Items */}
      <Card title="Critical Items (Low Stock)" className="border-l-4 border-l-red-500">
        {criticalItems.length === 0 ? (
          <p className="text-gray-600 text-center py-4">No critical items found</p>
        ) : (
          <div className="space-y-3">
            {criticalItems.map((item) => (
              <div key={item.id} className="flex justify-between items-center p-3 bg-red-50 rounded">
                <div>
                  <div className="font-medium text-red-900">{item.item_name}</div>
                  <div className="text-sm text-red-700">
                    {item.quantity} {item.unit} remaining (Min: {item.reorder_level})
                  </div>
                </div>
                <div className="text-sm text-red-600 font-medium">
                  {item.location}
                </div>
              </div>
            ))}
            
            <div className="pt-2 border-t border-red-200">
              <Button 
                variant="danger" 
                size="sm" 
                className="w-full"
                onClick={() => onReload && onReload({ low_stock: true })}
              >
                View All Critical Items
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Expiring Items */}
      <Card title="Expiring Soon (Next 30 Days)" className="border-l-4 border-l-yellow-500">
        {expiringItems.length === 0 ? (
          <p className="text-gray-600 text-center py-4">No expiring items found</p>
        ) : (
          <div className="space-y-3">
            {expiringItems.map((item) => (
              <div key={item.id} className="flex justify-between items-center p-3 bg-yellow-50 rounded">
                <div>
                  <div className="font-medium text-yellow-900">{item.item_name}</div>
                  <div className="text-sm text-yellow-700">
                    Expires: {new Date(item.expiry_date).toLocaleDateString()}
                  </div>
                </div>
                <div className="text-sm text-yellow-600 font-medium">
                  {(() => {
                    const days = Math.ceil((new Date(item.expiry_date) - new Date()) / (1000 * 60 * 60 * 24));
                    return days <= 0 ? 'EXPIRED' : `${days} days`;
                  })()}
                </div>
              </div>
            ))}
            
            <div className="pt-2 border-t border-yellow-200">
              <Button 
                variant="secondary" 
                size="sm" 
                className="w-full border-yellow-300 text-yellow-700 hover:bg-yellow-50"
                onClick={() => onReload && onReload({ expiring: true })}
              >
                View All Expiring Items
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
};

export default InventoryQuickActions;
