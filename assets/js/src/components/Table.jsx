import React from 'react';

/** Row-actions column in the phone card view */
const isActionsColumn = (column) =>
  column.accessor === 'actions' || column.isActions === true || column.header === 'Actions';

const Table = ({ 
  columns, 
  data, 
  onRowClick,
  emptyMessage = "No data available",
  className = "",
  striped = true,
  // Pagination props
  pagination = false,
  currentPage = 1,
  totalPages = 1,
  itemsPerPage = 10,
  totalItems = 0,
  onPageChange,
  onItemsPerPageChange,
  showItemsPerPageSelector = true,
  showExportButtons = false,
  onExport,
  onPrint
}) => {
  // Ensure data is an array and not empty
  const safeData = Array.isArray(data) ? data : [];
  
  if (safeData.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        {emptyMessage}
        {pagination && (
          <div className="flex flex-col sm:flex-row justify-between items-center mt-4 pt-4 border-t border-gray-200 gap-4">
            <div className="text-sm text-gray-700 font-medium">
              Showing 0 of {totalItems} items
            </div>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className={`${className}`}>
      {/* Phones (< 640px): one card per row. The actions column (accessor
          'actions' or isActions: true) becomes full-width 44px buttons. */}
      <ul className="sm:hidden space-y-3">
        {safeData.map((row, rowIndex) => {
          const fields = columns.filter((c) => !isActionsColumn(c) && !c.hideOnMobile);
          const actions = columns.filter(isActionsColumn);
          return (
            <li
              key={rowIndex}
              className={`bg-white p-4 rounded-lg border border-gray-200 shadow-sm ${onRowClick ? 'cursor-pointer active:bg-gray-50' : ''}`}
              onClick={() => onRowClick && onRowClick(row, rowIndex)}
            >
              <dl className="divide-y divide-gray-100">
                {fields.map((column, colIndex) => (
                  <div key={colIndex} className="flex flex-wrap justify-between gap-x-3 gap-y-0.5 py-2 first:pt-0">
                    <dt className="text-sm font-medium text-gray-500">{column.label || column.header}</dt>
                    <dd className="text-sm text-gray-900 min-w-0 text-right break-words [overflow-wrap:anywhere] ml-auto">
                      {column.render ? column.render(row, rowIndex) : row[column.accessor]}
                    </dd>
                  </div>
                ))}
              </dl>
              {actions.length > 0 && (
                <div
                  className="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-2 [&_div]:flex [&_div]:flex-wrap [&_div]:gap-2 [&_div>*]:!ml-0 [&_div>*]:!mr-0 [&_a]:min-h-11 [&_button]:min-h-11 [&_a]:flex-1 [&_button]:flex-1 [&_a]:inline-flex [&_button]:inline-flex [&_a]:items-center [&_button]:items-center [&_a]:justify-center [&_button]:justify-center [&_a]:px-4 [&_button]:px-4"
                  onClick={(e) => e.stopPropagation()}
                >
                  {actions.map((column, i) => (
                    <React.Fragment key={i}>{column.render ? column.render(row, rowIndex) : null}</React.Fragment>
                  ))}
                </div>
              )}
            </li>
          );
        })}
      </ul>

      <div className="hidden sm:block overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              {columns.map((column, index) => (
                <th
                  key={index}
                  scope="col"
                  className={`px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${column.className || ''}`}
                >
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className={`bg-white divide-y divide-gray-200 ${striped ? '' : 'divide-y-0'}`}>
            {safeData.map((row, rowIndex) => (
              <tr
                key={rowIndex}
                className={`${striped && rowIndex % 2 === 1 ? 'bg-gray-50' : 'bg-white'} ${
                  onRowClick ? 'hover:bg-gray-100 cursor-pointer' : ''
                }`}
                onClick={() => onRowClick && onRowClick(row, rowIndex)}
              >
                {columns.map((column, colIndex) => (
                  <td
                    key={colIndex}
                    className={`px-4 lg:px-6 py-4 whitespace-nowrap text-sm ${isActionsColumn(column) ? '[&_a]:min-h-7 [&_button]:min-h-7' : ''} ${column.cellClassName || ''}`}
                  >
                    {column.render ? column.render(row, rowIndex) : row[column.accessor]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      
      {/* Export Buttons */}
      {showExportButtons && safeData.length > 0 && (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
          <div className="flex space-x-3">
            {onExport && (
              <button 
                onClick={onExport}
                className="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export to CSV
              </button>
            )}
            {onPrint && (
              <button 
                onClick={onPrint}
                className="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Report
              </button>
            )}
          </div>
        </div>
      )}
      
      {/* Pagination */}
      {pagination && (
        <div className="flex flex-col sm:flex-row justify-between items-center mt-4 pt-4 border-t border-gray-200 gap-4">
          
          <div className="flex flex-col sm:flex-row items-center gap-4">
            <div className="text-sm text-gray-700 font-medium">
              Showing <span className="font-semibold">{((currentPage - 1) * itemsPerPage) + 1}</span>{' '}
              to <span className="font-semibold">{Math.min(currentPage * itemsPerPage, totalItems)}</span>{' '}
              of <span className="font-semibold">{totalItems}</span> items
            </div>
            
            {/* Items per page selector */}
            {showItemsPerPageSelector && onItemsPerPageChange && (
              <div className="flex items-center gap-2 text-sm">
                <label htmlFor="itemsPerPage" className="text-gray-700 font-medium">
                  Items per page:
                </label>
                <select
                  id="itemsPerPage"
                  value={itemsPerPage}
                  onChange={(e) => {
                    onItemsPerPageChange(parseInt(e.target.value));
                  }}
                  className="border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value={10}>10</option>
                  <option value={20}>20</option>
                  <option value={50}>50</option>
                  <option value={100}>100</option>
                </select>
              </div>
            )}
          </div>
          
          {/* Navigation buttons - only show when there are multiple pages */}
          {totalPages > 1 && (
          <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0">
            <div className="flex items-center justify-center w-full sm:w-auto">
              <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  onClick={() => onPageChange && onPageChange(Math.max(currentPage - 1, 1))}
                  disabled={currentPage === 1}
                  className={`relative inline-flex items-center justify-center min-w-11 min-h-11 sm:min-w-0 sm:min-h-0 px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium ${
                    currentPage === 1 
                      ? 'text-gray-300 cursor-not-allowed' 
                      : 'text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <span className="sr-only">Previous</span>
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
                
                {/* Phones: "3 / 12" instead of the row of page numbers */}
                <span className="sm:hidden relative inline-flex items-center px-4 border border-gray-300 bg-white text-sm font-medium text-gray-700 whitespace-nowrap">
                  {currentPage} / {totalPages}
                </span>
                {/* Page numbers */}
                <span className="hidden sm:contents">
                {(() => {
                  const pagesToShow = 5;
                  const pages = [];
                  let startPage = Math.max(1, currentPage - Math.floor(pagesToShow / 2));
                  let endPage = Math.min(totalPages, startPage + pagesToShow - 1);
                  
                  if (endPage - startPage + 1 < pagesToShow) {
                    startPage = Math.max(1, endPage - pagesToShow + 1);
                  }
                  
                  // First page and ellipsis
                  if (startPage > 1) {
                    pages.push(
                      <button 
                        key="page-1"
                        onClick={() => onPageChange && onPageChange(1)}
                        className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                      >
                        1
                      </button>
                    );
                    if (startPage > 2) {
                      pages.push(
                        <span key="ellipsis-1" className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">
                          ...
                        </span>
                      );
                    }
                  }
                  
                  // Page numbers
                  for (let i = startPage; i <= endPage; i++) {
                    pages.push(
                      <button
                        key={`page-${i}`}
                        onClick={() => onPageChange && onPageChange(i)}
                        className={`relative inline-flex items-center px-3 py-2 border ${
                          currentPage === i
                            ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                            : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'
                        } text-sm font-medium`}
                      >
                        {i}
                      </button>
                    );
                  }
                  
                  // Last page and ellipsis
                  if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                      pages.push(
                        <span key="ellipsis-2" className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">
                          ...
                        </span>
                      );
                    }
                    pages.push(
                      <button
                        key={`page-${totalPages}`}
                        onClick={() => onPageChange && onPageChange(totalPages)}
                        className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                      >
                        {totalPages}
                      </button>
                    );
                  }
                  
                  return pages;
                })()}
                </span>
                
                <button
                  onClick={() => onPageChange && onPageChange(Math.min(currentPage + 1, totalPages))}
                  disabled={currentPage === totalPages}
                  className={`relative inline-flex items-center justify-center min-w-11 min-h-11 sm:min-w-0 sm:min-h-0 px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium ${
                    currentPage === totalPages 
                      ? 'text-gray-300 cursor-not-allowed' 
                      : 'text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  <span className="sr-only">Next</span>
                  <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                  </svg>
                </button>
              </nav>
            </div>
          </div>
          )}
        </div>
      )}
    </div>
  );
};

export default Table;
