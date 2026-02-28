import { Flex, Tag, Tree } from 'antd';
import type { TreeDataNode } from 'antd';
import { Globe, Building2, GitBranch, MapPin, type LucideIcon } from 'lucide-react';

export interface ScopeTreeBadge {
    label: string;
}

export interface ScopeTreeNode {
    id: string | null;
    name: string;
    type: string;
    children?: ScopeTreeNode[];
    badges?: ScopeTreeBadge[];
}

export interface ScopeTreeSelectedScope {
    type: string;
    id: string | null;
}

export interface ScopeTreeProps {
    nodes: ScopeTreeNode[];
    selectedScope?: ScopeTreeSelectedScope;
    onSelect?: (scope: ScopeTreeSelectedScope) => void;
    defaultExpandDepth?: number;
    iconMap?: Record<string, LucideIcon>;
}

const DEFAULT_ICON_MAP: Record<string, LucideIcon> = {
    global: Globe,
    organization: Building2,
    branch: GitBranch,
    location: MapPin,
};

function getIcon(type: string, iconMap?: Record<string, LucideIcon>): LucideIcon {
    if (iconMap?.[type]) return iconMap[type];
    return DEFAULT_ICON_MAP[type] ?? Globe;
}

function toKey(type: string, id: string | null): string {
    return `${type}::${id ?? ''}`;
}

function parseKey(key: string): ScopeTreeSelectedScope {
    const [type, id] = key.split('::');
    return { type, id: id || null };
}

function convertToTreeData(
    nodes: ScopeTreeNode[],
    iconMap?: Record<string, LucideIcon>,
): TreeDataNode[] {
    return nodes.map((node) => {
        const Icon = getIcon(node.type, iconMap);
        const title = (
            <Flex align="center" gap={4}>
                <span>{node.name}</span>
                {node.badges?.map((badge, i) => (
                    <Tag key={i}>
                        {badge.label}
                    </Tag>
                ))}
            </Flex>
        );

        return {
            key: toKey(node.type, node.id),
            title,
            icon: <Icon size={14} />,
            children: node.children ? convertToTreeData(node.children, iconMap) : undefined,
        };
    });
}

function collectExpandedKeys(
    nodes: ScopeTreeNode[],
    depth: number,
    maxDepth: number,
): string[] {
    if (depth >= maxDepth) return [];
    const keys: string[] = [];
    for (const node of nodes) {
        if (node.children && node.children.length > 0) {
            keys.push(toKey(node.type, node.id));
            keys.push(...collectExpandedKeys(node.children, depth + 1, maxDepth));
        }
    }
    return keys;
}

export function ScopeTree({
    nodes,
    selectedScope,
    onSelect,
    defaultExpandDepth = 1,
    iconMap,
}: ScopeTreeProps) {
    const treeData = convertToTreeData(nodes, iconMap);
    const defaultExpandedKeys = collectExpandedKeys(nodes, 0, defaultExpandDepth);
    const selectedKeys = selectedScope
        ? [toKey(selectedScope.type, selectedScope.id)]
        : [];

    const handleSelect = (keys: React.Key[]) => {
        if (keys.length > 0 && onSelect) {
            onSelect(parseKey(String(keys[0])));
        }
    };

    return (
        <Tree
            treeData={treeData}
            selectedKeys={selectedKeys}
            defaultExpandedKeys={defaultExpandedKeys}
            onSelect={handleSelect}
            showIcon
            blockNode
        />
    );
}
