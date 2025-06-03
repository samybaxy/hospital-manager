import React, { useState, useEffect } from 'react';
import Card from './Card';
import Button from './Button';
import LoadingState from './LoadingState';
import Alert from './Alert';
import Table from './Table';
import Badge from './Badge';
import inventoryService from '../services/inventoryService';
import { usePermissions } from '../hooks/usePermissions.jsx';

const InventoryReports = ({ onRefresh }) => {
  const permissions = usePermissions();
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  
  // Report data
  const [reportData, setReportData] = useState(null);
  const [reportType, setReportType] = useState('summary');
  const [inventoryItems, setInventoryItems] = useState([]);
  const [categories, setCategories] = useState([]);
  
  // Filter states
  const [filters, setFilters] = useState({
    date_from: '',
    date_to: '',
    category_id: '',
    location: '',
    supplier_id: ''
  });

  useEffect(() => {
    loadInitialData();
  }, []);

  useEffect(() => {
    if (reportType) {
      generateReport(reportType);
    }
  }, [reportType, filters]);

  const loadInitialData = async () => {
    try {
      setLoading(true);
      const [itemsResponse, categoriesResponse] = await Promise.all([
        inventoryService.getItems(),
        inventoryService.getCategories()
      ]);

      // Handle nested response structure for inventory items
      if (itemsResponse.success) {
        const items = itemsResponse.data?.items || itemsResponse.data?.data || itemsResponse.data || [];
        setInventoryItems(Array.isArray(items) ? items : []);
      } else {
        // Handle direct response without success flag
        const items = Array.isArray(itemsResponse) ? itemsResponse : 
                     Array.isArray(itemsResponse.data) ? itemsResponse.data :
                     Array.isArray(itemsResponse.data?.data) ? itemsResponse.data.data : [];
        setInventoryItems(Array.isArray(items) ? items : []);
      }
      
      if (categoriesResponse.success) {
        setCategories(categoriesResponse.data || []);
      } else {
        // Handle direct response without success flag
        const categories = Array.isArray(categoriesResponse) ? categoriesResponse : categoriesResponse.data || [];
        setCategories(Array.isArray(categories) ? categories : []);
      }
    } catch (error) {
      console.error('Error loading initial data:', error);
      setError('Failed to load initial data');
    } finally {
      setLoading(false);
    }
  };

  const generateReport = async (type) => {
    try {
      setGenerating(true);
      setError(null);
      
      let data = [];
      
      switch (type) {
        case 'summary':
          data = generateSummaryReport();
          break;
        case 'critical':
          data = generateCriticalStockReport();
          break;
        case 'expiring':
          data = generateExpiringItemsReport();
          break;
        case 'category':
          data = generateCategoryReport();
          break;
        case 'valuation':
          data = generateValuationReport();
          break;
        default:
          data = [];
      }
      
      setReportData(data);
    } catch (error) {
      console.error('Error generating report:', error);
      setError('Failed to generate report');
    } finally {
      setGenerating(false);
    }
  };

  const generateSummaryReport = () => {
    if (!Array.isArray(inventoryItems)) {
      return [];
    }
    
    let items = [...inventoryItems];
    
    // Apply filters
    if (filters.category_id) {
      items = items.filter(item => item.category_id === parseInt(filters.category_id));
    }
    if (filters.location) {
      items = items.filter(item => item.location?.toLowerCase().includes(filters.location.toLowerCase()));
    }

    return items.map(item => ({
      id: item.ID, // Use uppercase ID field from WordPress database
      name: item.item_name,
      category: item.category || 'N/A',
      current_stock: item.quantity || 0,
      minimum_stock: item.reorder_level || 0,
      maximum_stock: item.maximum_stock || 0,
      unit: item.unit || 'pcs',
      location: item.location || 'N/A',
      status: getStockStatus(item),
      value: (item.quantity || 0) * (item.cost || 0)
    }));
  };

  const generateCriticalStockReport = () => {
    if (!Array.isArray(inventoryItems)) {
      return [];
    }
    
    return inventoryItems
      .filter(item => {
        const currentStock = item.quantity || 0;
        const minimumStock = item.reorder_level || 0;
        return currentStock <= minimumStock;
      })
      .map(item => ({
        id: item.ID, // Use uppercase ID field from WordPress database
        name: item.item_name,
        category: item.category || 'N/A',
        current_stock: item.quantity || 0,
        minimum_stock: item.reorder_level || 0,
        shortage: (item.reorder_level || 0) - (item.quantity || 0),
        unit: item.unit || 'pcs',
        location: item.location || 'N/A',
        priority: (item.quantity || 0) === 0 ? 'Critical' : 'Low'
      }));
  };

  const generateExpiringItemsReport = () => {
    if (!Array.isArray(inventoryItems)) {
      return [];
    }
    
    const today = new Date();
    const thirtyDaysFromNow = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
    
    return inventoryItems
      .filter(item => {
        if (!item.expiry_date) return false;
        const expiryDate = new Date(item.expiry_date);
        return expiryDate <= thirtyDaysFromNow;
      })
      .map(item => {
        const expiryDate = new Date(item.expiry_date);
        const daysToExpiry = Math.ceil((expiryDate - today) / (24 * 60 * 60 * 1000));
        
        return {
          id: item.ID, // Use uppercase ID field from WordPress database
          name: item.item_name,
          category: item.category || 'N/A',
          current_stock: item.quantity || 0,
          expiry_date: item.expiry_date,
          days_to_expiry: daysToExpiry,
          batch_number: item.batch_number || 'N/A',
          status: daysToExpiry <= 0 ? 'Expired' : daysToExpiry <= 7 ? 'Critical' : 'Warning'
        };
      })
      .sort((a, b) => a.days_to_expiry - b.days_to_expiry);
  };

  const generateCategoryReport = () => {
    if (!Array.isArray(inventoryItems)) {
      return [];
    }
    
    const categoryStats = {};
    
    inventoryItems.forEach(item => {
      const categoryName = item.category_name || 'Uncategorized';
      if (!categoryStats[categoryName]) {
        categoryStats[categoryName] = {
          category: categoryName,
          total_items: 0,
          total_stock: 0,
          total_value: 0,
          low_stock_items: 0
        };
      }
      
      categoryStats[categoryName].total_items++;
      categoryStats[categoryName].total_stock += item.current_stock || 0;
      categoryStats[categoryName].total_value += (item.current_stock || 0) * (item.unit_cost || 0);
      
      if ((item.current_stock || 0) <= (item.minimum_stock || 0)) {
        categoryStats[categoryName].low_stock_items++;
      }
    });
    
    return Object.values(categoryStats);
  };

  const generateValuationReport = () => {
    if (!Array.isArray(inventoryItems)) {
      return [];
    }
    
    return inventoryItems.map(item => ({
      id: item.ID, // Use uppercase ID field from WordPress database
      name: item.item_name,
      category: item.category || 'N/A',
      current_stock: item.quantity || 0,
      unit_cost: item.cost || 0,
      total_value: (item.quantity || 0) * (item.cost || 0),
      unit: item.unit || 'pcs',
      location: item.location || 'N/A'
    })).sort((a, b) => b.total_value - a.total_value);
  };

  const getStockStatus = (item) => {
    const currentStock = item.current_stock || 0;
    const minimumStock = item.minimum_stock || 0;
    
    if (currentStock === 0) return 'Out of Stock';
    if (currentStock <= minimumStock) return 'Low Stock';
    return 'In Stock';
  };

  const getStatusBadgeVariant = (status) => {
    switch (status) {
      case 'Out of Stock':
      case 'Critical':
      case 'Expired':
        return 'destructive';
      case 'Low Stock':
      case 'Warning':
        return 'warning';
      default:
        return 'default';
    }
  };

  const downloadCSV = () => {
    if (!reportData || reportData.length === 0) {
      setError('No data to download');
      return;
    }

    const headers = Object.keys(reportData[0]).join(',');
    const rows = reportData.map(row => 
      Object.values(row).map(value => 
        typeof value === 'string' && value.includes(',') ? `"${value}"` : value
      ).join(',')
    );
    
    const csvContent = [headers, ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.href = url;
    link.download = `inventory_${reportType}_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    setSuccess('Report downloaded successfully');
  };

  const handleFilterChange = (name, value) => {
    setFilters(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const renderReportContent = () => {
    if (!reportData || reportData.length === 0) {
      return (
        <div className="text-center py-8 text-gray-500">
          No data available for the selected report type and filters.
        </div>
      );
    }

    const columns = Object.keys(reportData[0]).map(key => ({
      key,
      label: key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
      render: (value, row) => {
        if (key === 'status' || key === 'priority') {
          return <Badge variant={getStatusBadgeVariant(value)}>{value}</Badge>;
        }
        if (key.includes('value') || key.includes('cost')) {
          return typeof value === 'number' ? `₦${value.toLocaleString()}` : value;
        }
        if (key.includes('date')) {
          return value ? new Date(value).toLocaleDateString() : 'N/A';
        }
        return value;
      }
    }));

    return (
      <Table
        data={reportData}
        columns={columns}
        pagination={true}
        searchable={true}
      />
    );
  };

  const reportTypes = [
    { value: 'summary', label: 'Inventory Summary', description: 'Overview of all inventory categories' },
    { value: 'critical', label: 'Critical Items Report', description: 'Items below reorder level' },
    { value: 'expiring', label: 'Expiring Items Report', description: 'Items expiring in the next 30 days' },
    { value: 'category', label: 'Category Analysis', description: 'Breakdown by item categories' },
    { value: 'valuation', label: 'Inventory Valuation', description: 'Total value of inventory items' }
  ];

  const processCategoryData = (items) => {
    const categories = {};
    
    items.forEach(item => {
      if (!categories[item.category]) {
        categories[item.category] = {
          total_items: 0,
          total_quantity: 0,
          total_value: 0,
          low_stock_items: 0
        };
      }
      
      categories[item.category].total_items++;
      categories[item.category].total_quantity += parseInt(item.quantity);
      categories[item.category].total_value += item.cost ? parseFloat(item.cost) * parseInt(item.quantity) : 0;
      
      if (parseInt(item.quantity) <= parseInt(item.reorder_level)) {
        categories[item.category].low_stock_items++;
      }
    });
    
    return categories;
  };

  const processValuationData = (items) => {
    const total_value = items.reduce((sum, item) => {
      return sum + (item.cost ? parseFloat(item.cost) * parseInt(item.quantity) : 0);
    }, 0);
    
    const total_items = items.length;
    const total_quantity = items.reduce((sum, item) => sum + parseInt(item.quantity), 0);
    
    return {
      total_value,
      total_items,
      total_quantity,
      average_value_per_item: total_items > 0 ? total_value / total_items : 0
    };
  };

  const downloadReport = () => {
    const reportContent = generateReportContent();
    const blob = new Blob([reportContent], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `inventory_${reportType}_report_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  };

  const generateReportContent = () => {
    if (!reportData) return '';
    
    const header = `Inventory ${reportTypes.find(t => t.value === reportType)?.label || 'Report'}\n`;
    const date = `Generated on: ${new Date().toLocaleDateString()}\n`;
    const separator = '='.repeat(50) + '\n\n';
    
    let content = header + date + separator;
    
    if (reportType === 'summary') {
      content += `Total Items: ${reportData.total_items}\n`;
      content += `In Stock: ${reportData.in_stock}\n`;
      content += `Low Stock: ${reportData.low_stock}\n`;
      content += `Out of Stock: ${reportData.out_of_stock}\n`;
    } else if (reportType === 'category') {
      Object.entries(reportData).forEach(([category, data]) => {
        content += `${category}:\n`;
        content += `  Total Items: ${data.total_items}\n`;
        content += `  Total Quantity: ${data.total_quantity}\n`;
        content += `  Total Value: ₦${data.total_value.toLocaleString()}\n`;
        content += `  Low Stock Items: ${data.low_stock_items}\n\n`;
      });
    } else if (reportType === 'valuation') {
      content += `Total Inventory Value: ₦${reportData.total_value.toLocaleString()}\n`;
      content += `Total Items: ${reportData.total_items}\n`;
      content += `Total Quantity: ${reportData.total_quantity}\n`;
      content += `Average Value per Item: ₦${reportData.average_value_per_item.toFixed(2)}\n`;
    } else if (Array.isArray(reportData)) {
      reportData.forEach(item => {
        content += `${item.item_name} (${item.category})\n`;
        content += `  Quantity: ${item.quantity} ${item.unit}\n`;
        content += `  Location: ${item.location}\n`;
        if (item.expiry_date) {
          content += `  Expiry: ${new Date(item.expiry_date).toLocaleDateString()}\n`;
        }
        content += '\n';
      });
    }
    
    return content;
  };

  return (
    <div className="space-y-6">
      {/* Error and Success Alerts */}
      {error && (
        <Alert type="error" className="mb-4">
          {error}
        </Alert>
      )}

      {success && (
        <Alert type="success" className="mb-4">
          {success}
        </Alert>
      )}

      {/* Report Type Selection */}
      <Card title="Generate Inventory Report">
        <div className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">
              Select Report Type
            </label>
            <div className="grid grid-cols-1 gap-3">{reportTypes.map((type) => (
              <label key={type.value} className="flex items-start">
                <input
                  type="radio"
                  value={type.value}
                  checked={reportType === type.value}
                  onChange={(e) => setReportType(e.target.value)}
                  className="mt-1 mr-3"
                />
                <div>
                  <div className="font-medium">{type.label}</div>
                  <div className="text-sm text-gray-600">{type.description}</div>
                </div>
              </label>
            ))}
          </div>
        </div>

        {/* Generate Button */}
        <div className="flex justify-center">
          <Button
            variant="primary"
            onClick={() => generateReport(reportType)}
            disabled={generating}
          >
            {generating ? 'Generating...' : 'Generate Report'}
          </Button>
        </div>

        {/* Report Results */}
        {reportData && (
          <Card title="Report Results">
            <div className="space-y-4">
              {reportType === 'summary' && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-blue-600">{reportData.total_items}</div>
                    <div className="text-sm text-gray-600">Total Items</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">{reportData.in_stock}</div>
                    <div className="text-sm text-gray-600">In Stock</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-yellow-600">{reportData.low_stock}</div>
                    <div className="text-sm text-gray-600">Low Stock</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-red-600">{reportData.out_of_stock}</div>
                    <div className="text-sm text-gray-600">Out of Stock</div>
                  </div>
                </div>
              )}

              {reportType === 'category' && (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Low Stock</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {Object.entries(reportData).map(([category, data]) => (
                        <tr key={category}>
                          <td className="px-6 py-4 whitespace-nowrap font-medium">{category}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{data.total_items}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{data.total_quantity}</td>
                          <td className="px-6 py-4 whitespace-nowrap">₦{data.total_value.toLocaleString()}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{data.low_stock_items}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {reportType === 'valuation' && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">₦{reportData.total_value.toLocaleString()}</div>
                    <div className="text-sm text-gray-600">Total Value</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-blue-600">{reportData.total_items}</div>
                    <div className="text-sm text-gray-600">Total Items</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-purple-600">{reportData.total_quantity}</div>
                    <div className="text-sm text-gray-600">Total Quantity</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-indigo-600">₦{reportData.average_value_per_item.toFixed(2)}</div>
                    <div className="text-sm text-gray-600">Avg Value/Item</div>
                  </div>
                </div>
              )}

              {(reportType === 'critical' || reportType === 'expiring') && Array.isArray(reportData) && (
                <div className="max-h-64 overflow-y-auto">
                  {reportData.length === 0 ? (
                    <p className="text-center text-gray-600 py-4">No items found</p>
                  ) : (
                    <div className="space-y-2">
                      {reportData.map((item) => (
                        <div key={item.id} className="p-3 bg-gray-50 rounded">
                          <div className="font-medium">{item.item_name}</div>
                          <div className="text-sm text-gray-600">
                            {item.quantity} {item.unit} - {item.location}
                            {item.expiry_date && ` - Expires: ${new Date(item.expiry_date).toLocaleDateString()}`}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              <div className="flex justify-end">
                <Button variant="secondary" onClick={downloadReport}>
                  Download Report
                </Button>
              </div>
            </div>
          </Card>
        )}
        </div>
      </Card>
    </div>
  );
};

export default InventoryReports;
