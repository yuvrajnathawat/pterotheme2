import{me as T}from"/assets/entry.j54mtbby.js";import{kf as F}from"/assets/entry.q99xpgr7.js";import{Af as J,Bf as U}from"/assets/entry.fqyvsr1c.js";import{Rk as Y}from"/assets/entry.va1026yc.js";import{$k as X,_k as q}from"/assets/entry.jvjvcmey.js";import{cl as P}from"/assets/entry.d5x3f9ta.js";import{dl as W}from"/assets/entry.90252mjk.js";var Q=W(P(),1);function g(A){return(G)=>{return(H,...K)=>J`
            @media (min-width: ${A[G]}px) {
                ${J(H,...K)}
            }
        `}}var N=g({xs:0,sm:640,md:768,lg:1024,xl:1280,xxl:1920});var Z=W(F(),1);var w=U.div.attrs({className:"fixed z-50 overflow-auto flex w-full inset-0"})`
    background: rgba(0, 0, 0, 0.7);
`,B=U.div.attrs({className:"relative flex flex-col w-full m-auto"})`
    max-width: 95%;
    max-height: calc(100vh - 8rem);
    ${N("md")`max-width: 75%`};
    ${N("lg")`max-width: 50%`};

    ${(A)=>A.alignTop&&J`
            margin-top: 20%;
            ${N("md")`margin-top: 10%`};
        `};

    margin-bottom: auto;

    & > .close-icon {
        position: absolute;
        right: 0px;
        padding: 0.5rem;
        color: white;
        cursor: pointer;
        opacity: 0.5;
        transition: all 150ms linear;
        top: -2.5rem;

        &:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        & > svg {
            width: 1.5rem;
            height: 1.5rem;
        }
    }
`,u=({visible:A,appear:G,dismissable:H,showSpinnerOverlay:K,top:_=!0,closeOnBackground:$=!0,closeOnEscape:V=!0,onDismissed:I,children:C})=>{let L=(H||!0)&&!K;return Q.useEffect(()=>{if(!L||!V)return;let z=(R)=>{if(R.key==="Escape"&&A)I()};return window.addEventListener("keydown",z),()=>{window.removeEventListener("keydown",z)}},[L,V,A,I]),q(T,{in:A,timeout:150,appear:G||!0,unmountOnExit:!0,onExited:()=>I(),children:q(w,{onClick:(z)=>z.stopPropagation(),onContextMenu:(z)=>z.stopPropagation(),onMouseDown:(z)=>{if(L&&$){if(z.stopPropagation(),z.target===z.currentTarget)I()}},children:X(B,{alignTop:_,children:[L&&q("div",{className:"close-icon",onClick:()=>I(),children:q("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor",children:q("path",{strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"2",d:"M6 18L18 6M6 6l12 12"})})}),K&&q(T,{timeout:150,appear:!0,in:!0,children:q("div",{className:"absolute w-full h-full rounded-lg flex items-center justify-center",style:{background:"hsla(211, 10%, 53%, 0.35)",zIndex:9999},children:q(Y,{})})}),q("div",{className:"transition-all duration-150",children:C})]})})})},E=({children:A,...G})=>{let H=Q.useRef(document.getElementById("modal-portal"));return Z.createPortal(q(u,{...G,children:A}),H.current)},D=E;
export{w as ub,D as vb};
