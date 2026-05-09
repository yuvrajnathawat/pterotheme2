/**
 * Shim to bridge React 16's global JSX namespace with the React.JSX namespace
 * expected by newer libraries like react-i18next v16+.
 *
 * React 18+ moved JSX types under the React namespace (React.JSX), but React 16's
 * @types/react only declares the global JSX namespace. This re-exports the global
 * JSX namespace under React.JSX so that libraries referencing React.JSX resolve correctly.
 */
import 'react';

declare module 'react' {
    namespace JSX {
        // Re-export from global JSX to ensure compatibility
        interface IntrinsicElements extends globalThis.JSX.IntrinsicElements { }
        interface IntrinsicAttributes extends globalThis.JSX.IntrinsicAttributes { }
        interface IntrinsicClassAttributes<T> extends globalThis.JSX.IntrinsicClassAttributes<T> { }

        interface Element extends globalThis.JSX.Element { }
        type ElementClass = globalThis.JSX.ElementClass;
        interface ElementAttributesProperty extends globalThis.JSX.ElementAttributesProperty { }
        interface ElementChildrenAttribute extends globalThis.JSX.ElementChildrenAttribute { }

        type LibraryManagedAttributes<C, P> = globalThis.JSX.LibraryManagedAttributes<C, P>;
    }
}
