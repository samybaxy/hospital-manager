import React from 'react';
import { useResponsiveTable } from '../hooks/useResponsiveTable';

const pagerButton =
  'inline-flex items-center justify-center min-h-11 sm:min-h-9 px-4 sm:px-3 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed';

/** The row-actions column: id 'actions' (or meta.isActions). */
const isActionsColumn = (column) => column.id === 'actions' || column.columnDef.meta?.isActions === true;

/** Label for a field in the phone card view (header text, meta.label, or the id). */
const columnLabel = (column) => {
  const def = column.columnDef;
  if (def.meta?.label) return def.meta.label;
  if (typeof def.header === 'string') return def.header;
  return String(column.id || '')
    .replace(/[_.]+/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/^./, (c) => c.toUpperCase());
};

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
      {/* Mobile Card View (< 640px): one card per row, label above/beside value,
          the actions column as full-width 44px buttons at the bottom */}
      <div className="block sm:hidden">
        <ul className="space-y-3">
          {table.getRowModel().rows.map((row) => {
            const cells = row.getVisibleCells().filter((cell) => !cell.column.columnDef.meta?.hideOnMobile);
            const actionCells = cells.filter((cell) => isActionsColumn(cell.column));
            const fieldCells = cells.filter((cell) => !isActionsColumn(cell.column));
            return (
              <li
                key={row.id}
                className={`bg-white p-4 rounded-lg border border-gray-200 shadow-sm ${
                  onRowClick ? 'cursor-pointer active:bg-gray-50' : ''
                }`}
                onClick={() => onRowClick && onRowClick(row.original)}
              >
                <dl className="divide-y divide-gray-100">
                  {fieldCells.map((cell) => (
                    <div key={cell.id} className="flex flex-wrap justify-between gap-x-3 gap-y-0.5 py-2 first:pt-0">
                      <dt className="text-sm font-medium text-gray-500">{columnLabel(cell.column)}</dt>
                      <dd className="text-sm text-gray-900 min-w-0 text-right break-words [overflow-wrap:anywhere] ml-auto">
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </dd>
                    </div>
                  ))}
                </dl>
                {actionCells.length > 0 && (
                  <div
                    className="mt-3 pt-3 border-t border-gray-100 [&_div]:flex [&_div]:w-full [&_div]:flex-wrap [&_div]:gap-2 [&_div>*]:!ml-0 [&_div>*]:!mr-0 [&_a]:min-h-11 [&_button]:min-h-11 [&_a]:flex-1 [&_button]:flex-1 [&_a]:justify-center [&_button]:justify-center [&_a]:px-4 [&_button]:px-4 [&_a]:text-sm [&_button]:text-sm"
                    onClick={(e) => e.stopPropagation()}
                  >
                    {actionCells.map((cell) => (
                      <div key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</div>
                    ))}
                  </div>
                )}
              </li>
            );
          })}
        </ul>
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
                      } ${isActionsColumn(cell.column) ? '[&_a]:min-h-7 [&_button]:min-h-7' : ''}`}>
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

      {/* Pagination: Prev / page / Next on phones (First/Last from sm) */}
      {showPagination && enablePagination && table.getPageCount() > 0 && (
        <nav className="flex flex-col sm:flex-row justify-between items-center gap-3 pt-2 sm:pt-4" aria-label="Pagination">
          <p className="text-sm text-gray-700 text-center">
            Showing {table.getState().pagination.pageIndex * table.getState().pagination.pageSize + 1} to{' '}
            {Math.min(
              (table.getState().pagination.pageIndex + 1) * table.getState().pagination.pageSize,
              table.getFilteredRowModel().rows.length
            )}{' '}
            of {table.getFilteredRowModel().rows.length} results
          </p>

          <div className="flex w-full sm:w-auto items-center justify-between sm:justify-end gap-2">
            <button
              className={`${pagerButton} hidden sm:inline-flex`}
              onClick={() => table.setPageIndex(0)}
              disabled={!table.getCanPreviousPage()}
            >
              First
            </button>
            <button
              className={pagerButton}
              onClick={() => table.previousPage()}
              disabled={!table.getCanPreviousPage()}
            >
              <span aria-hidden="true" className="sm:hidden">‹&nbsp;</span>Previous
            </button>
            <span className="px-2 text-sm font-medium text-gray-700 whitespace-nowrap">
              {table.getState().pagination.pageIndex + 1} / {table.getPageCount()}
            </span>
            <button
              className={pagerButton}
              onClick={() => table.nextPage()}
              disabled={!table.getCanNextPage()}
            >
              Next<span aria-hidden="true" className="sm:hidden">&nbsp;›</span>
            </button>
            <button
              className={`${pagerButton} hidden sm:inline-flex`}
              onClick={() => table.setPageIndex(table.getPageCount() - 1)}
              disabled={!table.getCanNextPage()}
            >
              Last
            </button>
          </div>
        </nav>
      )}
    </div>
  );
};

export default ResponsiveTable;
