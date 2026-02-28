import { router } from '@inertiajs/react';
import { Badge, Button, Drawer, Flex, Input, Select, Tag } from 'antd';
import { SlidersHorizontal } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react';

/* ── Types ────────────────────────────────────────── */

type FilterValue = string | number | Record<string, string> | null | undefined;
type FilterRecord = Record<string, FilterValue>;

/* ── Helpers ───────────────────────────────────────── */

export function buildFilterParams(
    currentFilters: FilterRecord,
    changes: {
        set?: Record<string, string | undefined>;
        setAdvanced?: Record<string, string | undefined>;
        removeAdvancedKey?: string;
    } = {},
): FilterRecord {
    const params: FilterRecord = {};

    // Copy top-level params (skip 'filter' and 'page')
    for (const [k, v] of Object.entries(currentFilters)) {
        if (k === 'filter' || k === 'page') continue;
        if (v !== undefined && v !== null && v !== '') params[k] = String(v);
    }

    // Apply top-level changes
    if (changes.set) {
        for (const [k, v] of Object.entries(changes.set)) {
            if (v && v !== '') params[k] = v;
            else delete params[k];
        }
    }

    // Build filter object
    const filter: Record<string, string> = {};

    if (changes.setAdvanced !== undefined) {
        for (const [k, v] of Object.entries(changes.setAdvanced)) {
            if (v !== undefined && v !== '') filter[k] = v;
        }
    } else {
        const existing = currentFilters.filter;
        if (existing && typeof existing === 'object') {
            for (const [k, v] of Object.entries(existing)) {
                if (v !== undefined && v !== null && v !== '') filter[k] = String(v);
            }
        }
        if (changes.removeAdvancedKey) {
            delete filter[changes.removeAdvancedKey];
        }
    }

    if (Object.keys(filter).length > 0) params.filter = filter;

    // Reset to page 1
    delete params.page;

    return params;
}

/* ── Context ───────────────────────────────────────── */

type FilterContext = {
    routeUrl: string;
    currentFilters: FilterRecord;
    applyFilter: (key: string, value: string | undefined) => void;
    removeAdvancedFilter: (key: string) => void;
};

const FilterCtx = createContext<FilterContext | null>(null);

function useFilterContext(): FilterContext {
    const ctx = useContext(FilterCtx);
    if (!ctx) throw new Error('Filter components must be used inside <Filters>');
    return ctx;
}

/* ── Filters (main wrapper) ────────────────────────── */

type FiltersProps = {
    routeUrl: string;
    currentFilters: FilterRecord;
    children: ReactNode;
};

export function Filters({ routeUrl, currentFilters, children }: FiltersProps) {
    const navigate = useCallback(
        (params: FilterRecord) => {
            router.get(routeUrl, params, { preserveState: true, preserveScroll: true });
        },
        [routeUrl],
    );

    const applyFilter = useCallback(
        (key: string, value: string | undefined) => {
            navigate(buildFilterParams(currentFilters, { set: { [key]: value } }));
        },
        [currentFilters, navigate],
    );

    const removeAdvancedFilter = useCallback(
        (key: string) => {
            navigate(buildFilterParams(currentFilters, { removeAdvancedKey: key }));
        },
        [currentFilters, navigate],
    );

    const ctx = useMemo(
        () => ({ routeUrl, currentFilters, applyFilter, removeAdvancedFilter }),
        [routeUrl, currentFilters, applyFilter, removeAdvancedFilter],
    );

    return (
        <FilterCtx.Provider value={ctx}>
            <Flex wrap gap={8} align="center">{children}</Flex>
        </FilterCtx.Provider>
    );
}

/* ── FilterSearch ──────────────────────────────────── */

type FilterSearchProps = {
    filterKey: string;
    placeholder?: string;
    style?: React.CSSProperties;
};

export function FilterSearch({ filterKey, placeholder, style }: FilterSearchProps) {
    const { currentFilters, applyFilter } = useFilterContext();

    return (
        <Input.Search
            placeholder={placeholder}
            defaultValue={(currentFilters[filterKey] as string) ?? ''}
            onSearch={(value) => applyFilter(filterKey, value || undefined)}
            allowClear
            enterButton
            style={style}
        />
    );
}

/* ── FilterSelect ──────────────────────────────────── */

type FilterSelectOption = {
    value: string;
    label: string;
};

type FilterSelectProps = {
    filterKey: string;
    options: FilterSelectOption[];
    allLabel?: string;
};

export function FilterSelect({ filterKey, options, allLabel = 'All' }: FilterSelectProps) {
    const { currentFilters, applyFilter } = useFilterContext();

    return (
        <Select
            value={(currentFilters[filterKey] as string) ?? '__all__'}
            onChange={(value) => applyFilter(filterKey, value === '__all__' ? undefined : value)}
            options={[
                { value: '__all__', label: allLabel },
                ...options,
            ]}
        />
    );
}

/* ── FilterAdvancedButton ──────────────────────────── */

type FilterAdvancedButtonProps = {
    label?: string;
    onClick: () => void;
};

export function FilterAdvancedButton({ label, onClick }: FilterAdvancedButtonProps) {
    const { currentFilters } = useFilterContext();
    const filterObj = currentFilters.filter;
    const activeCount = filterObj && typeof filterObj === 'object'
        ? Object.values(filterObj).filter((v) => v !== undefined && v !== null && v !== '').length
        : 0;

    return (
        <Badge count={activeCount} size="small">
            <Button icon={<SlidersHorizontal size={14} />} onClick={onClick}>
                {label ?? 'Filters'}
            </Button>
        </Badge>
    );
}

/* ── FilterDrawer ──────────────────────────────────── */

type FilterDrawerProps = {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    onApply: () => void;
    onReset: () => void;
};

export function FilterDrawer({ open, onClose, title, children, onApply, onReset }: FilterDrawerProps) {
    return (
        <Drawer
            title={title ?? 'Advanced Filters'}
            placement="right"
            size={360}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="space-between">
                    <Button onClick={onReset}>Reset</Button>
                    <Button type="primary" onClick={onApply}>Apply</Button>
                </Flex>
            }
        >
            <Flex vertical gap={16}>
                {children}
            </Flex>
        </Drawer>
    );
}

/* ── FilterChips ───────────────────────────────────── */

type FilterChipsProps = {
    labels: Record<string, string>;
    valueLabels?: Record<string, Record<string, string>>;
    /** Explicit filters — required when used outside <Filters> */
    currentFilters?: FilterRecord;
    /** Explicit remove handler — required when used outside <Filters> */
    onRemove?: (key: string) => void;
};

export function FilterChips({ labels, valueLabels, currentFilters: filtersProp, onRemove }: FilterChipsProps) {
    const ctx = useContext(FilterCtx);
    const currentFilters = filtersProp ?? ctx?.currentFilters;
    const removeAdvancedFilter = onRemove ?? ctx?.removeAdvancedFilter;
    if (!currentFilters) return null;
    const filterObj = currentFilters.filter;
    if (!filterObj || typeof filterObj !== 'object') return null;

    const entries = Object.entries(filterObj).filter(
        ([, v]) => v !== undefined && v !== null && v !== '',
    );

    if (entries.length === 0) return null;

    return (
        <Flex wrap gap={4}>
            {entries.map(([key, value]) => {
                const label = labels[key] ?? key;
                const displayValue = valueLabels?.[key]?.[String(value)] ?? String(value);
                return (
                    <Tag key={key} closable onClose={() => removeAdvancedFilter?.(key)}>
                        {label}: {displayValue}
                    </Tag>
                );
            })}
        </Flex>
    );
}
