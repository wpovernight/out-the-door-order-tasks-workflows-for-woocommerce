/**
 * Public API of the SDK runtime.
 *
 * This barrel is compiled to `assets/js/sdk.js` and exposed on the page as
 * `window.wpo.aom.sdk`. Both the core bundles and add-on plugins (Pro) link
 * against that single global instead of bundling their own copy.
 *
 * Anything exported here is a public contract, removing or renaming an export
 * is a breaking change for add-ons and needs a core version bump.
 *
 * Keep the exports named: `export *` does not forward default exports, and the
 * webpack external maps every `@sdk/*` request onto this one flat namespace.
 */

// Components.
export * from './components/AsyncMultiSelectField';
export * from './components/DatePicker';
export * from './components/Dialog';
export * from './components/FieldOptionDropdownField';
export * from './components/LoadingSkeleton';
export * from './components/Pager';
export * from './components/SidebarModal';
export * from './components/SortIcon';
export * from './components/TaskActionMenu';
export * from './components/TaskCard';
export * from './components/TaskCardSkeleton';
export * from './components/TaskForm';
export * from './components/TaskFormSkeleton';
export * from './components/Toast';

// Context providers and their hooks.
export * from './context/DialogContext';
export * from './context/SidebarModalContext';
export * from './context/StatusRoleContext';
export * from './context/TaskContext';
export * from './context/ToastContext';

// Hooks.
export * from './hooks/getInitialStatusRoles';
export * from './hooks/useAsyncLoader';
export * from './hooks/useLocalized';
export * from './hooks/useOnClickOutside';
export * from './hooks/useScrollable';
export * from './hooks/useTaskFormModal';
export * from './hooks/useTaskSort';

// Types.
export * from './types/customOrderStatus';
export * from './types/fulfillment';
export * from './types/task';
export * from './types/wooFulfillment';

// Utils, including the REST client used by every view.
export * from './utils/api';
export * from './utils/colorUtils';
export * from './utils/fieldUtils';
export * from './utils/textUtils';
