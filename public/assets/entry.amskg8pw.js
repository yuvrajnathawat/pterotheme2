import{cl as r}from"/assets/entry.d5x3f9ta.js";import{dl as o}from"/assets/entry.90252mjk.js";var e=o(r(),1);function d(i=600){let[n,s]=e.useState(!1);return e.useEffect(()=>{let t=()=>s(window.innerWidth<i);return t(),window.addEventListener("resize",t),()=>window.removeEventListener("resize",t)},[i]),n}
export{d as Eb};
