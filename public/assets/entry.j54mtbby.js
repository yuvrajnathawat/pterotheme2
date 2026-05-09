import{oe as w}from"/assets/entry.cz67n79m.js";import{Bf as v}from"/assets/entry.fqyvsr1c.js";import{_k as k}from"/assets/entry.jvjvcmey.js";import{cl as G}from"/assets/entry.d5x3f9ta.js";import{dl as E}from"/assets/entry.90252mjk.js";var z=E(G(),1);var H=v.div`
    .fade-enter,
    .fade-exit,
    .fade-appear {
        will-change: opacity;
    }

    .fade-enter,
    .fade-appear {
        opacity: 0;

        &.fade-enter-active,
        &.fade-appear-active {
            opacity: 1;
            transition-property: opacity;
            transition-timing-function: cubic-bezier(0.4, 0, 1, 1);
            transition-duration: ${(b)=>b.$timeout}ms;
        }
    }

    .fade-exit {
        opacity: 1;

        &.fade-exit-active {
            opacity: 0;
            transition-property: opacity;
            transition-timing-function: cubic-bezier(0.4, 0, 1, 1);
            transition-duration: ${(b)=>b.$timeout}ms;
        }
    }
`,A=({timeout:b,children:B,...D})=>{let q=z.useRef(null);return k(H,{$timeout:b,children:k(w,{timeout:b,classNames:"fade",nodeRef:q,...D,children:k("div",{ref:q,children:B})})})};A.displayName="Fade";var M=A;
export{M as me};
