// Test file to verify all inventory components can be imported correctly
import InventoryTransactions from './components/InventoryTransactions.jsx';
import InventorySuppliers from './components/InventorySuppliers.jsx';
import InventoryReorders from './components/InventoryReorders.jsx';
import InventoryReports from './components/InventoryReports.jsx';

console.log('All inventory components imported successfully:');
console.log('InventoryTransactions:', typeof InventoryTransactions);
console.log('InventorySuppliers:', typeof InventorySuppliers);
console.log('InventoryReorders:', typeof InventoryReorders);
console.log('InventoryReports:', typeof InventoryReports);

export {
  InventoryTransactions,
  InventorySuppliers,
  InventoryReorders,
  InventoryReports
};
