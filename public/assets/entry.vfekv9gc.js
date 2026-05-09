import{cl as a}from"/assets/entry.d5x3f9ta.js";import{dl as S}from"/assets/entry.90252mjk.js";var t=S(a(),1);function u(e,i){let[n,s]=t.useState(()=>{try{let r=localStorage.getItem(e);if(r===null)return i;return JSON.parse(r)}catch(r){return i}});return t.useEffect(()=>{localStorage.setItem(e,JSON.stringify(n))},[e,n]),[n,s]}
export{u as Db};
