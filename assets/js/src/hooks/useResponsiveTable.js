import { useState, useEffect, useMemo } from 'react';
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  flexRender,
} from '@tanstack/react-table';

export const useResponsiveTable = ({
  data = [],
  columns = [],
  initialSorting = [],
  initialFilters = {},
  pageSize = 10,
  enableSorting = true,
  enableFiltering = true,
  enablePagination = true,
}) => {
  const [sorting, setSorting] = useState(initialSorting);
  const [columnFilters, setColumnFilters] = useState([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize,
  });

  // Responsive breakpoint state
  const [isMobile, setIsMobile] = useState(false);
  const [isTablet, setIsTablet] = useState(false);

  // Monitor screen size for responsive behavior
  useEffect(() => {
    const checkScreenSize = () => {
      const width = window.innerWidth;
      setIsMobile(width < 640);
      setIsTablet(width >= 640 && width < 1024);
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  // Filter columns based on screen size
  const responsiveColumns = useMemo(() => {
    return columns.map(column => ({
      ...column,
      // Add responsive visibility
      meta: {
        ...column.meta,
        hideOnMobile: column.meta?.hideOnMobile || false,
        hideOnTablet: column.meta?.hideOnTablet || false,
      }
    })).filter(column => {
      if (isMobile && column.meta?.hideOnMobile) return false;
      if (isTablet && column.meta?.hideOnTablet) return false;
      return true;
    });
  }, [columns, isMobile, isTablet]);

  const table = useReactTable({
    data,
    columns: responsiveColumns,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: enableFiltering ? getFilteredRowModel() : undefined,
    getSortedRowModel: enableSorting ? getSortedRowModel() : undefined,
    getPaginationRowModel: enablePagination ? getPaginationRowModel() : undefined,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    onGlobalFilterChange: setGlobalFilter,
    onPaginationChange: setPagination,
    state: {
      sorting,
      columnFilters,
      globalFilter,
      pagination,
    },
    enableSorting,
    enableFiltering,
  });

  return {
    table,
    isMobile,
    isTablet,
    flexRender,
    // Expose state setters for external control
    setSorting,
    setColumnFilters,
    setGlobalFilter,
    setPagination,
    // Current state values
    sorting,
    columnFilters,
    globalFilter,
    pagination,
  };
};

export default useResponsiveTable;
