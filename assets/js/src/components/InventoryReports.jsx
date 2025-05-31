import React, { useState } from 'react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';
import inventoryService from '../services/inventoryService';

const InventoryReports = ({ isOpen, onClose }) => {
  const [reportType, setReportType] = useState('summary');
  const [generating, setGenerating] = useState(false);
  const [reportData, setReportData] = useState(null);

  const reportTypes = [
    { value: 'summary', label: 'Inventory Summary', description: 'Overview of all inventory categories' },
    { value: 'critical', label: 'Critical Items Report', description: 'Items below reorder level' },
    { value: 'expiring', label: 'Expiring Items Report', description: 'Items expiring in the next 30 days' },
    { value: 'category', label: 'Category Analysis', description: 'Breakdown by item categories' },
    { value: 'valuation', label: 'Inventory Valuation', description: 'Total value of inventory items' }
  ];

  const generateReport = async () => {
    setGenerating(true);
    try {
      let data;
      
      switch (reportType) {
        case 'summary':
          data = await inventoryService.getSummary();
          break;
        case 'critical':
          data = await inventoryService.getCriticalItems();
          break;
        case 'expiring':
          data = await inventoryService.getExpiringItems(30);
          break;
        case 'category':
          data = await inventoryService.getItems({ per_page: -1 });
          data = processCategoryData(data.data || []);
          break;
        case 'valuation':
          data = await inventoryService.getItems({ per_page: -1 });
          data = processValuationData(data.data || []);
          break;
        default:
          data = await inventoryService.getSummary();
      }
      
      setReportData(data);
    } catch (error) {
      console.error('Error generating report:', error);
    } finally {
      setGenerating(false);
    }
  };

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
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Generate Inventory Report"
      size="lg"
    >
      <div className="space-y-6">
        {/* Report Type Selection */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-3">
            Select Report Type
          </label>
          <div className="grid grid-cols-1 gap-3">
            {reportTypes.map((type) => (
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
            onClick={generateReport}
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

        {/* Footer */}
        <div className="flex justify-end space-x-3 pt-6 border-t">
          <Button variant="secondary" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </Modal>
  );
};

export default InventoryReports;
