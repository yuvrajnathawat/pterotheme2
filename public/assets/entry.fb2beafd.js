import{nf as w}from"/assets/entry.fqyvsr1c.js";var z=()=>{return w((b)=>b.flashes)},L=(b)=>{let{addFlash:q,clearFlashes:B,clearAndAddHttpError:C}=z();return{addFlash:(j)=>q({...j,key:b}),addError:(j,D)=>q({key:b,message:j,title:D,type:"error"}),clearFlashes:()=>B(b),clearAndAddHttpError:(j)=>C({key:b,error:j})}};var N=z;
export{L as Ee,N as Fe};
