import { Tag } from 'antd';
import { Globe, Building2, GitBranch, MapPin, type LucideIcon } from 'lucide-react';

const DEFAULT_STYLES: Record<string, { color: string; icon: LucideIcon }> = {
    global: { color: 'blue', icon: Globe },
    organization: { color: 'purple', icon: Building2 },
    branch: { color: 'orange', icon: GitBranch },
    location: { color: 'green', icon: MapPin },
};

export interface ScopeTypeBadgeProps {
    type: string;
    label: string;
}

export function ScopeTypeBadge({ type, label }: ScopeTypeBadgeProps) {
    const style = DEFAULT_STYLES[type] ?? DEFAULT_STYLES.global;
    const Icon = style.icon;

    return (
        <Tag color={style.color} icon={<Icon size={12} />}>
            {label}
        </Tag>
    );
}
