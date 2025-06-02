/**
 * Debugging utilities for inventory data
 */

import inventoryService from '../services/inventoryService';

/**
 * Comprehensive debug report for inventory data
 * Use this in the developer console to diagnose issues
 */
export async function debugInventoryData() {
  try {
    console.group('🔍 Inventory Data Debug Report');
    
    // 1. Get summary data
    console.log('Fetching summary data...');
    const summary = await inventoryService.getSummary();
    console.log('Summary data:', summary);
    
    if (!summary) {
      console.error('⚠️ Summary data is null or undefined');
    } else {
      // Check expected properties
      const expectedProps = ['total_items', 'in_stock', 'low_stock', 'out_of_stock'];
      const missingProps = expectedProps.filter(prop => summary[prop] === undefined);
      
      if (missingProps.length > 0) {
        console.error(`⚠️ Summary data is missing properties: ${missingProps.join(', ')}`);
      } else {
        console.log('✅ Summary data has all expected properties');
      }
      
      // Print summary stats
      console.table({
        'Total Items': summary.total_items || 0,
        'In Stock': summary.in_stock || 0,
        'Low Stock': summary.low_stock || 0,
        'Out of Stock': summary.out_of_stock || 0,
        'Math Check': `${summary.in_stock + summary.low_stock + summary.out_of_stock} = ${summary.total_items}?`
      });
      
      // Verify math
      const sum = (summary.in_stock || 0) + (summary.low_stock || 0) + (summary.out_of_stock || 0);
      if (sum !== summary.total_items) {
        console.error(`⚠️ Math error: Sum of categories (${sum}) doesn't equal total items (${summary.total_items})`);
      }
    }
    
    // 2. Get all inventory items
    console.log('Fetching inventory items...');
    const items = await inventoryService.getItems({ per_page: 1000 });
    console.log('Inventory items:', items);
    
    if (!items || !Array.isArray(items)) {
      console.error('⚠️ Items data is not an array');
    } else {
      // Count statuses manually
      const statuses = {};
      items.forEach(item => {
        const status = item.status || 'unknown';
        statuses[status] = (statuses[status] || 0) + 1;
      });
      
      console.log('Item counts by status:', statuses);
      
      // Check in_stock items specifically
      const inStockItems = items.filter(item => item.status === 'in_stock' && !item.low_stock);
      console.log(`Found ${inStockItems.length} items with status 'in_stock' and not low_stock`);
      
      if (inStockItems.length > 0 && inStockItems.length !== (summary?.in_stock || 0)) {
        console.error(`⚠️ Discrepancy: Found ${inStockItems.length} in-stock items, but summary reports ${summary?.in_stock || 0}`);
      }
    }
    
    // 3. Get critical items
    console.log('Fetching critical items...');
    const critical = await inventoryService.getCriticalItems();
    console.log('Critical items:', critical);
    
    // 4. Get service endpoints
    console.log('API Endpoints:');
    console.log('Summary URL:', inventoryService.getApiUrl('summary'));
    console.log('Items URL:', inventoryService.getApiUrl('items'));
    console.log('Critical URL:', inventoryService.getApiUrl('critical'));
    
  } catch (error) {
    console.error('Error in debug report:', error);
  } finally {
    console.groupEnd();
    console.log('Debug report complete. Check browser console for full details.');
  }
}

/**
 * Use this function to add and expose the debug method to the window object
 * This allows calling it from the browser console
 */
export function exposeDebugger() {
  window.debugInventory = debugInventoryData;
  console.log('Inventory debugger exposed. Call window.debugInventory() to run debug report.');
}

export default {
  debugInventoryData,
  exposeDebugger
};