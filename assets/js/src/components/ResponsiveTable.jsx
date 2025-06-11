import React from 'react';
import { useResponsiveTable } from '../hooks/useResponsiveTable';

const ResponsiveTable = ({
  data = [],
  columns = [],
  loading = false,
  emptyMessage = "No data available",
  onRowClick,
  enableSorting = true,
  enableFiltering = true,
  enablePagination = true,
  pageSize = 10,
  showPagination = true,
  className = "",
  containerClassName = "",
}) => {
  const {
    table,
    isMobile,
    isTablet,
    flexRender,
  } = useResponsiveTable({
    data,
    columns,
    pageSize,
    enableSorting,
    enableFiltering,
    enablePagination,
  });

  if (loading) {
    return (
      <div className="flex justify-center items-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary-500"></div>
        <span className="ml-3 text-gray-600">Loading...</span>
      </div>
    );
  }

  if (data.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg border border-gray-200">
        <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p className="text-lg font-medium mb-1">No data found</p>
        <p className="text-sm">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <div className={`space-y-4 ${containerClassName}`}>
      {/* Debug info - remove in production */}
      {process.env.NODE_ENV === 'development' && (
        <div className="text-xs text-gray-500 p-2 bg-yellow-50 rounded">
          Screen: {isMobile ? 'Mobile' : isTablet ? 'Tablet' : 'Desktop'} | 
          Width: {typeof window !== 'undefined' ? window.innerWidth : 'Unknown'}px |
          Columns: {table.getAllColumns().length}
        </div>
      )}
      
      {/* Mobile Card View (< 640px) */}
      <div className="block sm:hidden">
        <div className="space-y-3">
          {table.getRowModel().rows.map((row) => (
            <div
              key={row.id}
              className={`bg-white p-4 rounded-lg border border-gray-200 shadow-sm ${
                onRowClick ? 'cursor-pointer hover:bg-gray-50 active:bg-gray-100' : ''
              }`}
              onClick={() => onRowClick && onRowClick(row.original)}
            >
              {row.getVisibleCells().map((cell) => {
                const column = cell.column.columnDef;
                // Skip columns that should be hidden on mobile
                if (column.meta?.hideOnMobile) return null;
                
                return (
                  <div key={cell.id} className="flex justify-between items-start py-1.5 border-b border-gray-100 last:border-b-0">
                    <span className="text-sm font-medium text-gray-600 min-w-0 flex-1 pr-3">
                      {typeof column.header === 'string' ? column.header : 'Field'}:
                    </span>
                    <div className="text-sm text-gray-900 min-w-0 flex-1 text-right">
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </div>
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>

      {/* Tablet/Desktop Table View (>= 640px) */}
      <div className="hidden sm:block">
        <div className="overflow-x-auto rounded-lg border border-gray-200">
          <table className={`min-w-full divide-y divide-gray-200 ${className}`}>
            <thead className="bg-gray-50">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr key={headerGroup.id}>
                  {headerGroup.headers.map((header) => {
                    // Apply responsive hiding for tablet
                    const column = header.column.columnDef;
                    const hideOnTablet = isTablet && column.meta?.hideOnTablet;
                    
                    if (hideOnTablet) return null;
                    
                    return (
                      <th
                        key={header.id}
                        className={`px-4 lg:px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ${
                          column.meta?.headerAlign || 'text-left'
                        } ${
                          header.column.getCanSort() ? 'cursor-pointer select-none hover:bg-gray-100' : ''
                        }`}
                        onClick={header.column.getToggleSortingHandler()}
                      >
                        <div className={`flex items-center space-x-1 ${
                          column.meta?.headerAlign === 'text-center' ? 'justify-center' :
                          column.meta?.headerAlign === 'text-right' ? 'justify-end' : 'justify-start'
                        }`}>
                          <span>
                            {header.isPlaceholder
                              ? null
                              : flexRender(header.column.columnDef.header, header.getContext())
                            }
                          </span>
                          {header.column.getCanSort() && (
                            <span className="text-gray-400 ml-1">
                              {{
                                asc: '↑',
                                desc: '↓',
                              }[header.column.getIsSorted()] ?? '↕'}
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              ))}
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {table.getRowModel().rows.map((row, index) => (
                <tr
                  key={row.id}
                  className={`${
                    index % 2 === 0 ? 'bg-white' : 'bg-gray-50'
                  } hover:bg-blue-50 transition-colors ${
                    onRowClick ? 'cursor-pointer' : ''
                  }`}
                  onClick={() => onRowClick && onRowClick(row.original)}
                >
                  {row.getVisibleCells().map((cell) => {
                    // Apply responsive hiding for tablet
                    const column = cell.column.columnDef;
                    const hideOnTablet = isTablet && column.meta?.hideOnTablet;
                    
                    if (hideOnTablet) return null;
                    
                    return (
                      <td key={cell.id} className={`px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-900 ${
                        column.meta?.headerAlign || 'text-left'
                      }`}>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {showPagination && enablePagination && (
        <div className="flex flex-col sm:flex-row justify-between items-center gap-4 pt-4">
          <div className="text-sm text-gray-700">
            Showing {table.getState().pagination.pageIndex * table.getState().pagination.pageSize + 1} to{' '}
            {Math.min(
              (table.getState().pagination.pageIndex + 1) * table.getState().pagination.pageSize,
              table.getFilteredRowModel().rows.length
            )}{' '}
            of {table.getFilteredRowModel().rows.length} results
          </div>
          
          <div className="flex items-center space-x-2">
            <button
              className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => table.setPageIndex(0)}
              disabled={!table.getCanPreviousPage()}
            >
              First
            </button>
            <button
              className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => table.previousPage()}
              disabled={!table.getCanPreviousPage()}
            >
              Previous
            </button>
            <span className="px-3 py-2 text-sm font-medium text-gray-700">
              Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount()}
            </span>
            <button
              className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => table.nextPage()}
              disabled={!table.getCanNextPage()}
            >
              Next
            </button>
            <button
              className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => table.setPageIndex(table.getPageCount() - 1)}
              disabled={!table.getCanNextPage()}
            >
              Last
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ResponsiveTable;
