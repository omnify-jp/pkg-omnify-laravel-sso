/**
 * Custom ESLint rules for Ant Design v6.
 *
 * Rules:
 *   antd/no-deprecated-props — Catch all deprecated component props (extracted from antd v6 source).
 *   antd/no-drawer-width     — Drawer `width` is deprecated; use `size` instead.
 *   antd/require-form-prop   — <Form> must receive a `form` prop (Form.useForm()).
 */

// ---------------------------------------------------------------------------
// Deprecated prop map — extracted from antd v6.3.1 source deprecation warnings.
//
//   [oldProp, newProp]
//   When newProp contains a dot (e.g. "styles.body"), the replacement is a
//   nested object prop — no autofix is provided because the transform is
//   non-trivial.  Simple renames (no dot) get an autofix.
// ---------------------------------------------------------------------------

const DEPRECATED_PROPS = {
    Alert: [
        ['message', 'title'],
        ['closeText', 'closable.closeIcon'],
    ],
    Modal: [
        ['bodyStyle', 'styles.body'],
        ['maskStyle', 'styles.mask'],
        ['destroyOnClose', 'destroyOnHidden'],
        ['autoFocusButton', 'focusable.autoFocusButton'],
        ['focusTriggerAfterClose', 'focusable.focusTriggerAfterClose'],
        ['maskClosable', 'mask.closable'],
    ],
    Drawer: [
        ['headerStyle', 'styles.header'],
        ['bodyStyle', 'styles.body'],
        ['footerStyle', 'styles.footer'],
        ['contentWrapperStyle', 'styles.wrapper'],
        ['maskStyle', 'styles.mask'],
        ['drawerStyle', 'styles.section'],
        ['destroyInactivePanel', 'destroyOnHidden'],
        ['width', 'size'],
        ['height', 'size'],
    ],
    Tooltip: [
        ['overlayStyle', 'styles.root'],
        ['overlayInnerStyle', 'styles.container'],
        ['overlayClassName', 'classNames.root'],
        ['destroyTooltipOnHide', 'destroyOnHidden'],
    ],
    Popover: [
        ['overlayStyle', 'styles.root'],
        ['overlayInnerStyle', 'styles.container'],
        ['overlayClassName', 'classNames.root'],
        ['destroyTooltipOnHide', 'destroyOnHidden'],
    ],
    Descriptions: [
        ['labelStyle', 'styles.label'],
        ['contentStyle', 'styles.content'],
    ],
    Empty: [
        ['imageStyle', 'styles.image'],
    ],
    Image: [
        ['wrapperStyle', 'styles.root'],
    ],
    Spin: [
        ['tip', 'description'],
        ['wrapperClassName', 'classNames.root'],
    ],
    Steps: [
        ['labelPlacement', 'titlePlacement'],
        ['direction', 'orientation'],
    ],
    Tabs: [
        ['popupClassName', 'classNames.popup'],
        ['tabPosition', 'tabPlacement'],
        ['destroyInactiveTabPane', 'destroyOnHidden'],
    ],
    Space: [
        ['direction', 'orientation'],
        ['split', 'separator'],
    ],
    Slider: [
        ['tooltipPrefixCls', 'tooltip.prefixCls'],
        ['getTooltipPopupContainer', 'tooltip.getPopupContainer'],
        ['tipFormatter', 'tooltip.formatter'],
        ['tooltipPlacement', 'tooltip.placement'],
        ['tooltipVisible', 'tooltip.open'],
    ],
    Carousel: [
        ['dotPosition', 'dotPlacement'],
    ],
    Divider: [
        ['orientationMargin', 'styles.content.margin'],
    ],
    Transfer: [
        ['listStyle', 'styles.section'],
        ['operationStyle', 'styles.actions'],
        ['operations', 'actions'],
    ],
    TimePicker: [
        ['addon', 'renderExtraFooter'],
    ],
    Tag: [
        ['bordered', 'variant'],
    ],
    Select: [
        ['dropdownMatchSelectWidth', 'popupMatchSelectWidth'],
        ['dropdownRender', 'popupRender'],
        ['dropdownClassName', 'popupClassName'],
        ['dropdownStyle', 'popupStyle'],
        ['dropdownAlign', 'popupAlign'],
    ],
    TreeSelect: [
        ['dropdownMatchSelectWidth', 'popupMatchSelectWidth'],
        ['dropdownRender', 'popupRender'],
        ['dropdownClassName', 'popupClassName'],
        ['dropdownStyle', 'popupStyle'],
        ['dropdownAlign', 'popupAlign'],
    ],
    Cascader: [
        ['dropdownMatchSelectWidth', 'popupMatchSelectWidth'],
        ['dropdownRender', 'popupRender'],
        ['dropdownClassName', 'popupClassName'],
        ['dropdownStyle', 'popupStyle'],
        ['dropdownAlign', 'popupAlign'],
    ],
    AutoComplete: [
        ['dropdownMatchSelectWidth', 'popupMatchSelectWidth'],
        ['dropdownRender', 'popupRender'],
        ['dropdownClassName', 'popupClassName'],
        ['dropdownStyle', 'popupStyle'],
        ['dropdownAlign', 'popupAlign'],
    ],
};

/**
 * Check if a JSX element name matches a component name.
 * Handles both <Component> and <Namespace.Component>.
 */
function matchesComponent(jsxName, componentName) {
    if (jsxName.type === 'JSXIdentifier') {
        return jsxName.name === componentName;
    }
    if (jsxName.type === 'JSXMemberExpression') {
        return jsxName.property.name === componentName;
    }
    return false;
}

/** @type {import('eslint').ESLint.Plugin} */
const plugin = {
    rules: {
        // ---------------------------------------------------------------
        // antd/no-deprecated-props — comprehensive deprecated prop checker
        // ---------------------------------------------------------------
        'no-deprecated-props': {
            meta: {
                type: 'problem',
                docs: {
                    description: 'Disallow deprecated antd v6 component props',
                },
                fixable: 'code',
                messages: {
                    deprecated:
                        '`{{component}}` prop `{{oldProp}}` is deprecated in antd v6. Use `{{newProp}}` instead.',
                },
            },
            create(context) {
                return {
                    JSXOpeningElement(node) {
                        for (const [component, props] of Object.entries(DEPRECATED_PROPS)) {
                            if (!matchesComponent(node.name, component)) continue;

                            for (const [oldProp, newProp] of props) {
                                const attr = node.attributes.find(
                                    (a) => a.type === 'JSXAttribute' && a.name.name === oldProp,
                                );

                                if (!attr) continue;

                                const canAutofix = !newProp.includes('.');

                                context.report({
                                    node: attr,
                                    messageId: 'deprecated',
                                    data: { component, oldProp, newProp },
                                    fix: canAutofix
                                        ? (fixer) => fixer.replaceText(attr.name, newProp)
                                        : undefined,
                                });
                            }

                            break; // matched component, no need to check others
                        }
                    },
                };
            },
        },

        // ---------------------------------------------------------------
        // antd/no-drawer-width (kept for backward compat — covered by no-deprecated-props)
        // ---------------------------------------------------------------
        'no-drawer-width': {
            meta: {
                type: 'problem',
                docs: {
                    description: 'Disallow deprecated `width` prop on antd Drawer (use `size`)',
                },
                fixable: 'code',
                messages: {
                    noWidth:
                        'Drawer `width` is deprecated in antd v6. Use `size` instead. Example: <Drawer size={480}>',
                },
            },
            create(context) {
                return {
                    JSXOpeningElement(node) {
                        const { name } = node;
                        const isDrawer =
                            (name.type === 'JSXIdentifier' && name.name === 'Drawer') ||
                            (name.type === 'JSXMemberExpression' && name.property.name === 'Drawer');

                        if (!isDrawer) return;

                        const widthAttr = node.attributes.find(
                            (attr) => attr.type === 'JSXAttribute' && attr.name.name === 'width',
                        );

                        if (widthAttr) {
                            context.report({
                                node: widthAttr,
                                messageId: 'noWidth',
                                fix(fixer) {
                                    return fixer.replaceText(widthAttr.name, 'size');
                                },
                            });
                        }
                    },
                };
            },
        },

        // ---------------------------------------------------------------
        // antd/require-form-prop
        // ---------------------------------------------------------------
        'require-form-prop': {
            meta: {
                type: 'problem',
                docs: {
                    description: 'Require `form` prop on antd <Form> to prevent unconnected instances',
                },
                messages: {
                    missingForm:
                        '<Form> must have a `form` prop. Use `const [form] = Form.useForm()` and pass `<Form form={form}>`.',
                },
            },
            create(context) {
                return {
                    JSXOpeningElement(node) {
                        const { name } = node;

                        // Only match <Form>, not <Form.Item>, <Form.List>, etc.
                        if (name.type !== 'JSXIdentifier' || name.name !== 'Form') return;

                        const hasFormProp = node.attributes.some(
                            (attr) => attr.type === 'JSXAttribute' && attr.name.name === 'form',
                        );

                        if (!hasFormProp) {
                            context.report({ node, messageId: 'missingForm' });
                        }
                    },
                };
            },
        },
    },
};

export default plugin;
