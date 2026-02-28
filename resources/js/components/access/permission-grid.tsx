import { Checkbox, Table, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';

export interface PermissionDefinition {
    key: string;
    label: string;
}

export interface PermissionModule {
    key: string;
    label: string;
    permissions: PermissionDefinition[];
}

export interface PermissionGridLabels {
    moduleHeader: string;
    selectAll: string;
}

export interface PermissionGridProps {
    modules: PermissionModule[];
    selectedIds: string[];
    onChange?: (ids: string[]) => void;
    readOnly?: boolean;
    labels?: Partial<PermissionGridLabels>;
}

const defaultLabels: PermissionGridLabels = {
    moduleHeader: 'Module',
    selectAll: 'Select All',
};

export function buildPermissionId(moduleKey: string, permissionKey: string): string {
    return `${moduleKey}:${permissionKey}`;
}

function collectColumns(modules: PermissionModule[]): string[] {
    const seen = new Set<string>();
    const columns: string[] = [];
    for (const mod of modules) {
        for (const perm of mod.permissions) {
            if (!seen.has(perm.key)) {
                seen.add(perm.key);
                columns.push(perm.key);
            }
        }
    }
    return columns;
}

function getColumnLabel(modules: PermissionModule[], key: string): string {
    for (const mod of modules) {
        const perm = mod.permissions.find(p => p.key === key);
        if (perm) return perm.label;
    }
    return key;
}

interface RowData {
    key: string;
    module: PermissionModule;
}

export function PermissionGrid({
    modules,
    selectedIds,
    onChange,
    readOnly = false,
    labels: labelOverrides,
}: PermissionGridProps) {
    const labels = { ...defaultLabels, ...labelOverrides };
    const permColumns = collectColumns(modules);

    const togglePermission = (permId: string) => {
        if (readOnly || !onChange) return;
        if (selectedIds.includes(permId)) {
            onChange(selectedIds.filter(id => id !== permId));
        } else {
            onChange([...selectedIds, permId]);
        }
    };

    const toggleModule = (mod: PermissionModule) => {
        if (readOnly || !onChange) return;
        const modulePermIds = mod.permissions.map(p => buildPermissionId(mod.key, p.key));
        const allSelected = modulePermIds.every(id => selectedIds.includes(id));

        if (allSelected) {
            onChange(selectedIds.filter(id => !modulePermIds.includes(id)));
        } else {
            const newIds = new Set([...selectedIds, ...modulePermIds]);
            onChange(Array.from(newIds));
        }
    };

    const isModuleAllSelected = (mod: PermissionModule): boolean => {
        const modulePermIds = mod.permissions.map(p => buildPermissionId(mod.key, p.key));
        return modulePermIds.length > 0 && modulePermIds.every(id => selectedIds.includes(id));
    };

    const dataSource: RowData[] = modules.map((mod) => ({
        key: mod.key,
        module: mod,
    }));

    const columns: ColumnsType<RowData> = [
        {
            title: labels.moduleHeader,
            dataIndex: ['module', 'label'],
            render: (_: unknown, record: RowData) => (
                <Typography.Text strong>{record.module.label}</Typography.Text>
            ),
        },
        ...permColumns.map((colKey) => ({
            title: getColumnLabel(modules, colKey),
            key: colKey,
            width: 80,
            align: 'center' as const,
            render: (_: unknown, record: RowData) => {
                const modPermKeys = new Set(record.module.permissions.map(p => p.key));
                if (!modPermKeys.has(colKey)) {
                    return <Typography.Text type="secondary">-</Typography.Text>;
                }
                const permId = buildPermissionId(record.module.key, colKey);
                const checked = selectedIds.includes(permId);
                return (
                    <Checkbox
                        checked={checked}
                        onChange={() => togglePermission(permId)}
                        disabled={readOnly}
                    />
                );
            },
        })),
        ...(!readOnly
            ? [
                  {
                      title: labels.selectAll,
                      key: 'selectAll',
                      width: 80,
                      align: 'center' as const,
                      render: (_: unknown, record: RowData) => (
                          <Checkbox
                              checked={isModuleAllSelected(record.module)}
                              onChange={() => toggleModule(record.module)}
                          />
                      ),
                  },
              ]
            : []),
    ];

    return (
        <Table<RowData>
            columns={columns}
            dataSource={dataSource}
            pagination={false}
            size="small"
            bordered
        />
    );
}
